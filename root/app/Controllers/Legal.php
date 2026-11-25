<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * admin/legal/accept: the Terms of Service and Privacy Policy versions the signed-in client has still to accept
 * (legalPending(), from sign-in). App\Filters\AuthFilter keeps them on this page until they accept; the API records
 * the acceptance (v1/client/legal/accept).
 */
class Legal extends BaseController
{
    /**
     * GET admin/legal/accept: each pending document with its change summary and full text, and one checkbox
     */
    public function index(): string|RedirectResponse
    {
        $pending = legalPending();
        if ($pending === []) {
            return redirect()->to(base_url(needsBusiness() ? 'admin/create-business' : 'admin/dashboard'));
        }

        // the texts in the client's language; pending versions are always the current ones
        [$code, $body] = $this->callApi('v1/legal/current', []);
        $texts = $code === 200 && ($body['status'] ?? '') === 'success'
            ? array_column((array) ($body['data']['documents'] ?? []), null, 'public_id')
            : [];
        $documents = [];
        foreach ($pending as $version) {
            $documents[] = $version + ['body' => $texts[$version['public_id'] ?? '']['body'] ?? null];
        }

        return view('legal_accept', [
            'locale'    => $this->request->getLocale(),
            'slug'      => 'legal-accept',
            'documents' => $documents,
        ]);
    }

    /**
     * POST admin/legal/accept { agree }: accept every pending version. Responds with {status: 'success', redirect},
     * or {status: 'error', message, reload} when a newer version took effect since the page was shown.
     */
    public function accept(): ResponseInterface
    {
        if ($this->request->getPost('agree') === null) {
            return $this->failed(lang('Legal.accept.agree-required'), 422);
        }
        $pending = legalPending();
        if ($pending === []) {
            return $this->response->setJSON(['status' => 'success', 'redirect' => base_url('admin/dashboard')]);
        }

        [$code, $body] = $this->callApi('v1/client/legal/accept', ['versions' => array_column($pending, 'public_id')]);
        if ($code === 409) {
            // a newer version took effect meanwhile: show that one instead
            [$code, $body] = $this->callApi('v1/client/legal/pending', []);
            if ($code === 200 && ($body['status'] ?? '') === 'success') {
                session()->set('legal_pending', array_values((array) ($body['data']['legal_pending'] ?? [])));
            }
            return $this->failed(lang('Legal.accept.outdated'), 409, true);
        }
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->failed($body['message'] ?? lang('Legal.accept.failed'), $code === 200 ? 502 : $code);
        }

        $left = array_values((array) ($body['data']['legal_pending'] ?? []));
        session()->set('legal_pending', $left);

        return $this->response->setJSON([
            'status'   => 'success',
            'redirect' => base_url($left !== [] ? 'admin/legal/accept' : (needsBusiness() ? 'admin/create-business' : 'admin/dashboard')),
        ]);
    }

    private function failed(string $message, int $code, bool $reload = false): ResponseInterface
    {
        $code = ($code >= 400 && $code < 600) ? $code : 500;
        $body = ['status' => 'error', 'message' => $message];
        if ($reload) {
            $body['reload'] = true;
        }
        return $this->response->setStatusCode($code)->setJSON($body);
    }
}
