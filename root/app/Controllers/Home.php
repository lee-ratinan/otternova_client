<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class Home extends BaseController
{
    // Only clients (auth_master.account_type = 'C') may sign in to this system
    private const USER_TYPE = 'C';
    // Hard cap on the session lifetime, whatever the API returns
    private const MAX_SESSION_SECONDS = 8 * 3600;

    /**
     * Show the login form, or go straight to the dashboard when already signed in.
     */
    public function index(): string|RedirectResponse
    {
        if (hasActiveSession()) {
            return redirect()->to(base_url('admin/dashboard'));
        }
        $locale = $this->request->getLocale();
        $data   = [
            'locale' => $locale,
            'slug'   => 'login'
        ];
        return view('public_login', $data);
    }

    /**
     * POST login: forward the credentials to LINK_INTERNAL_API.
     * Responds with either {status: 'otp_required'} or {status: 'success', redirect}.
     */
    public function login(): ResponseInterface
    {
        $email    = trim((string) $this->request->getPost('email'));
        $password = (string) $this->request->getPost('password');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            return $this->fail(lang('Login.error.invalid-input'), 422);
        }

        [$code, $body] = $this->callApi('v1/login', [
            'email_address' => $email,
            'password'      => $password,
            'account_type'  => self::USER_TYPE,
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->fail($body['message'] ?? lang('Login.error.invalid-credentials'), $code === 200 ? 401 : $code);
        }

        $data    = $body['data'] ?? [];
        $session = session();
        $session->remove(['otp_token', 'otp_email']);

        if (! empty($data['otp_required'])) {
            // otp_mode = 'E': the API has emailed the code, keep the challenge token server-side only
            if (empty($data['otp_token'])) {
                return $this->fail(lang('Login.error.otp-start-failed'), 502);
            }
            $session->set([
                'otp_token' => $data['otp_token'],
                'otp_email' => $email,
            ]);
            return $this->response->setJSON([
                'status'  => 'otp_required',
                'message' => lang('Login.otp.sent', [$email]),
            ]);
        }

        // otp_mode = 'T' or 'X': signed in without OTP
        return $this->createSession($data);
    }

    /**
     * POST verify-otp: verify the emailed code with LINK_INTERNAL_API, then create the session.
     */
    public function verifyOtp(): ResponseInterface
    {
        $session  = session();
        $otpToken = $session->get('otp_token');
        if (empty($otpToken)) {
            return $this->fail(lang('Login.error.otp-expired'), 410);
        }

        $otp = preg_replace('/\D/', '', (string) $this->request->getPost('otp'));
        if (strlen($otp) !== 6) {
            return $this->fail(lang('Login.error.otp-format'), 422);
        }

        [$code, $body] = $this->callApi('v1/verify-otp', [
            'otp_token' => $otpToken,
            'otp'       => $otp,
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            // 410: the challenge is gone (expired or too many attempts), start over
            if ($code === 410) {
                $session->remove(['otp_token', 'otp_email']);
            }
            return $this->fail($body['message'] ?? lang('Login.error.otp-incorrect'), $code === 200 ? 401 : $code);
        }

        $session->remove(['otp_token', 'otp_email']);
        return $this->createSession($body['data'] ?? []);
    }

    /**
     * GET logout: revoke the JWT at LINK_INTERNAL_API (best effort), then end the session.
     */
    public function logout(): RedirectResponse
    {
        $session = session();
        $token   = $session->get('access_token');
        if (! empty($token)) {
            $this->callApi('v1/logout', [], $token);
        }
        $session->destroy();
        return redirect()->to(base_url());
    }

    public function createAccount(): string
    {
        $locale = $this->request->getLocale();
        $data   = [
            'locale' => $locale,
            'slug'   => 'create-account'
        ];
        return view('public_createAccount', $data);
    }
    public function forgotPassword(): string
    {
        $locale = $this->request->getLocale();
        $data   = [
            'locale' => $locale,
            'slug'   => 'forgot-password'
        ];
        return view('public_forgotPassword', $data);
    }

    public function resetPassword(): string
    {
        $locale = $this->request->getLocale();
        $data   = [
            'locale' => $locale,
            'slug'   => 'reset-password'
        ];
        return view('public_resetPassword', $data);
    }

    /**
     * Store the JWT and the user profile returned by LINK_INTERNAL_API in the session.
     */
    private function createSession(array $data): ResponseInterface
    {
        if (empty($data['access_token']) || empty($data['user'])) {
            return $this->fail(lang('Login.error.sign-in-failed'), 502);
        }

        $maxExpiry = time() + self::MAX_SESSION_SECONDS;
        $expiresAt = (int) ($data['expires_at'] ?? $maxExpiry);

        $session = session();
        $session->regenerate(true);
        $session->set([
            'access_token' => $data['access_token'],
            'expires_at'   => min($expiresAt, $maxExpiry),
            'user'         => $data['user'],
        ]);

        return $this->response->setJSON([
            'status'   => 'success',
            'redirect' => base_url('admin/dashboard'),
        ]);
    }

    /**
     * POST JSON to LINK_INTERNAL_API and return [http status, decoded body].
     */
    private function callApi(string $endpoint, array $payload, ?string $token = null): array
    {
        $headers = ['Accept' => 'application/json'];
        if ($token !== null) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        try {
            $response = service('curlrequest')->post(getenv('LINK_INTERNAL_API') . $endpoint, [
                'json'        => $payload,
                'headers'     => $headers,
                'timeout'     => 15,
                'http_errors' => false,
            ]);
            $code = $response->getStatusCode();
            $body = json_decode($response->getBody(), true);
            if (! is_array($body) || $code === 404 || $code >= 500) {
                log_message('error', 'LINK_INTERNAL_API ' . $endpoint . ' returned HTTP ' . $code);
                return [503, ['message' => lang('Login.error.service-unavailable')]];
            }
            return [$code, $body];
        } catch (Throwable $e) {
            log_message('error', 'LINK_INTERNAL_API ' . $endpoint . ' failed: ' . $e->getMessage());
            return [503, ['message' => lang('Login.error.service-unavailable')]];
        }
    }

    private function fail(string $message, int $code): ResponseInterface
    {
        $code = ($code >= 400 && $code < 600) ? $code : 500;
        return $this->response->setStatusCode($code)->setJSON([
            'status'  => 'error',
            'message' => $message,
        ]);
    }
}
