<?php

use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\App;

/**
 * The login flow against a stubbed LINK_INTERNAL_API, including the expired-password change.
 *
 * @internal
 */
final class LoginFlowTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const SIGNED_IN = [
        'otp_required' => false,
        'access_token' => 'test-token',
        'expires_at'   => 0,
        'user'         => ['id' => 1, 'email_address' => 'client@example.com', 'full_name' => 'Test Client'],
        'business'     => ['client_role' => 'O', 'client_status' => 'A', 'is_default_business' => 'Y', 'start_date' => '2026-11-01',
                           'business' => ['business_name' => 'Otter Cafe', 'business_slug' => 'otter-cafe-sg']],
    ];
    private const PASSWORD_EXPIRED = [
        'otp_required'             => false,
        'password_change_required' => true,
        'password_change_token'    => 'change-token',
    ];

    /** @var list<array{string, array}> endpoints and payloads sent to the API */
    private array $calls = [];

    protected function tearDown(): void
    {
        Services::reset();
        parent::tearDown();
    }

    /**
     * Answer the next API calls in order with [http status, body].
     */
    private function stubApi(array ...$replies): void
    {
        $curl = $this->createMock(CURLRequest::class);
        $curl->method('post')->willReturnCallback(function (string $url, array $options) use (&$replies) {
            $this->calls[] = [substr($url, strlen((string) getenv('LINK_INTERNAL_API'))), $options['json'] ?? []];
            [$code, $body] = array_shift($replies);
            return (new Response(new App()))->setStatusCode($code)->setBody(json_encode($body));
        });
        Services::injectMock('curlrequest', $curl);
    }

    private function success(array $data): array
    {
        return [200, ['status' => 'success', 'data' => $data]];
    }

    private function json($result): array
    {
        return json_decode($result->getJSON(), true);
    }

    public function testLoginWithoutOtpSignsInWhenPasswordIsValid(): void
    {
        $this->stubApi($this->success(['expires_at' => time() + 3600] + self::SIGNED_IN));

        $result = $this->post('login', ['email' => 'client@example.com', 'password' => 'secret-password']);

        $result->assertOK();
        $this->assertSame('success', $this->json($result)['status']);
        $this->assertSame('C', $this->calls[0][1]['account_type']);
        $result->assertSessionHas('access_token', 'test-token');
    }

    public function testLoginGoesToDashboardWithABusiness(): void
    {
        $this->stubApi($this->success(['expires_at' => time() + 3600] + self::SIGNED_IN));

        $result = $this->post('login', ['email' => 'client@example.com', 'password' => 'secret-password']);

        $this->assertSame(base_url('admin/dashboard'), $this->json($result)['redirect']);
        $result->assertSessionHas('business', self::SIGNED_IN['business']);
    }

    public function testLoginWithoutBusinessGoesToCreateBusiness(): void
    {
        $this->stubApi($this->success(['expires_at' => time() + 3600, 'business' => null] + self::SIGNED_IN));

        $result = $this->post('login', ['email' => 'client@example.com', 'password' => 'secret-password']);

        $result->assertOK();
        $this->assertSame(base_url('admin/create-business'), $this->json($result)['redirect']);
        $result->assertSessionHas('access_token', 'test-token');
        $this->assertArrayHasKey('business', $_SESSION);
        $this->assertNull($_SESSION['business']);
    }

    public function testLoginWithoutOtpAsksForNewPasswordWhenExpired(): void
    {
        $this->stubApi($this->success(self::PASSWORD_EXPIRED));

        $result = $this->post('login', ['email' => 'client@example.com', 'password' => 'secret-password']);

        $result->assertOK();
        $this->assertSame('password_change_required', $this->json($result)['status']);
        $result->assertSessionHas('password_change_token', 'change-token');
        $result->assertSessionMissing('access_token');
        // the token stays server-side
        $result->assertDontSee('change-token');
    }

    public function testOtpThenExpiredPasswordAsksForNewPassword(): void
    {
        $this->stubApi(
            $this->success(['otp_required' => true, 'otp_token' => 'otp-token']),
            $this->success(self::PASSWORD_EXPIRED),
        );

        $login = $this->post('login', ['email' => 'client@example.com', 'password' => 'secret-password']);
        $this->assertSame('otp_required', $this->json($login)['status']);
        $login->assertSessionMissing('password_change_token');

        $result = $this->withSession($_SESSION)->post('verify-otp', ['otp' => '123456']);

        $result->assertOK();
        $this->assertSame('password_change_required', $this->json($result)['status']);
        $this->assertSame(['v1/verify-otp', ['otp_token' => 'otp-token', 'otp' => '123456']], $this->calls[1]);
        $result->assertSessionHas('password_change_token', 'change-token');
        $result->assertSessionMissing('otp_token');
        $result->assertSessionMissing('access_token');
    }

    public function testWrongOtpDoesNotReachPasswordChange(): void
    {
        $this->stubApi([401, ['status' => 'error', 'message' => 'The code is incorrect. Please try again.']]);

        $result = $this->withSession(['otp_token' => 'otp-token'])->post('verify-otp', ['otp' => '000000']);

        $result->assertStatus(401);
        $result->assertSessionMissing('password_change_token');
        $result->assertSessionMissing('access_token');
    }

    public function testChangePasswordCreatesSession(): void
    {
        $this->stubApi($this->success(['expires_at' => time() + 3600] + self::SIGNED_IN));

        $result = $this->withSession(['password_change_token' => 'change-token'])
            ->post('change-password', ['new_password' => 'new-password-1', 'confirm_password' => 'new-password-1']);

        $result->assertOK();
        $this->assertSame(base_url('admin/dashboard'), $this->json($result)['redirect']);
        $this->assertSame(['v1/change-expired-password', ['password_change_token' => 'change-token', 'new_password' => 'new-password-1']], $this->calls[0]);
        $result->assertSessionHas('access_token', 'test-token');
        $result->assertSessionMissing('password_change_token');
    }

    public function testChangePasswordWithoutTokenIsRejected(): void
    {
        $this->stubApi();

        $result = $this->post('change-password', ['new_password' => 'new-password-1', 'confirm_password' => 'new-password-1']);

        $result->assertStatus(410);
        $this->assertSame([], $this->calls);
        $result->assertSessionMissing('access_token');
    }

    public function testChangePasswordChecksInputBeforeCallingApi(): void
    {
        $this->stubApi();
        $session = ['password_change_token' => 'change-token'];

        $this->withSession($session)->post('change-password', ['new_password' => 'short', 'confirm_password' => 'short'])
            ->assertStatus(422);
        $this->withSession($session)->post('change-password', ['new_password' => 'new-password-1', 'confirm_password' => 'new-password-2'])
            ->assertStatus(422);
        $this->assertSame([], $this->calls);
    }

    public function testExpiredChangeTokenStartsOver(): void
    {
        $this->stubApi([410, ['status' => 'error', 'message' => 'Expired.']]);

        $result = $this->withSession(['password_change_token' => 'change-token'])
            ->post('change-password', ['new_password' => 'new-password-1', 'confirm_password' => 'new-password-1']);

        $result->assertStatus(410);
        $result->assertSessionMissing('password_change_token');
        $result->assertSessionMissing('access_token');
    }
}
