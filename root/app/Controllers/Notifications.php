<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * The signed-in client's notifications, through LINK_INTERNAL_API v1/client/notifications/*: the header's dropdown
 * (assets/js/notification-center.js, on every page) and the notification centre page. Titles and messages come in
 * the page's language. Opening one marks it read, switches to its business when the client is working in another,
 * and goes to its target_url, a path in this app.
 */
class Notifications extends BaseController
{
    private const API = 'v1/client/notifications/';

    /**
     * GET admin/notifications: the notification centre, filled by its script
     * @return string
     */
    public function index(): string
    {
        return view('admin_notifications', [
            'locale' => $this->request->getLocale(),
            'slug'   => 'notifications',
            'user'   => (array) session('user'),
        ]);
    }

    /**
     * POST admin/notifications/latest: the newest few and the unread count, for the header's dropdown
     * @return ResponseInterface
     */
    public function latest(): ResponseInterface
    {
        return $this->relay('latest', []);
    }

    /**
     * POST admin/notifications/list { start, length, status }: one page for the notification centre
     * @return ResponseInterface
     */
    public function list(): ResponseInterface
    {
        $status = (string) $this->input('status');

        return $this->relay('list', [
            'start'   => (int) $this->input('start'),
            'length'  => (int) ($this->input('length') ?: 20),
            'filters' => in_array($status, ['unread', 'read'], true) ? ['status' => $status] : [],
        ]);
    }

    /**
     * GET admin/notifications/open/{public_id}: mark it read, switch to its business if need be, and go where it
     * points; back to the notification centre when it has nowhere to go, is gone, or its business cannot be opened
     * @param string $publicId
     * @return RedirectResponse
     */
    public function open(string $publicId): RedirectResponse
    {
        $centre        = redirect()->to(base_url('admin/notifications'));
        [$code, $body] = $this->callApi(self::API . 'read', ['public_id' => $publicId]);
        $notification  = $body['data']['notification'] ?? null;
        $target        = (string) ($notification['target_url'] ?? '');
        if ($code !== 200 || ! str_starts_with($target, '/') || str_starts_with($target, '//')) {
            return $centre;
        }

        $slug = (string) ($notification['business']['business_slug'] ?? '');
        if ($slug !== '' && $slug !== currentBusinessSlug()) {
            [$code, $body] = $this->callApi('v1/client/business/switch', ['business_slug' => $slug]);
            $business      = $body['data']['business'] ?? null;
            if ($code !== 200 || ($body['status'] ?? '') !== 'success' || ! is_array($business)) {
                return $centre;
            }
            session()->set('business', $business);
        }

        return redirect()->to(base_url(ltrim($target, '/')));
    }

    /**
     * POST admin/notifications/read-all
     * @return ResponseInterface
     */
    public function readAll(): ResponseInterface
    {
        return $this->relay('read-all', []);
    }

    /**
     * POST admin/notifications/delete { public_id }
     * @return ResponseInterface
     */
    public function delete(): ResponseInterface
    {
        return $this->relay('delete', ['public_id' => (string) $this->input('public_id')]);
    }

    /**
     * POST admin/notifications/delete-read
     * @return ResponseInterface
     */
    public function deleteRead(): ResponseInterface
    {
        return $this->relay('delete-read', []);
    }

    /**
     * The API's answer as { success, message, data } for the scripts
     * @param string $action
     * @param array  $payload
     * @return ResponseInterface
     */
    private function relay(string $action, array $payload): ResponseInterface
    {
        [$code, $body] = $this->callApi(self::API . $action, $payload);
        $ok            = $code === 200 && ($body['status'] ?? '') === 'success';

        return $this->response->setStatusCode($ok ? 200 : ($code === 200 ? 422 : $code))->setJSON([
            'success' => $ok,
            'message' => $body['message'] ?? ($ok ? '' : lang('Notifications.load-failed')),
            'data'    => $ok ? ($body['data'] ?? []) : null,
        ]);
    }

    /**
     * A field of the JSON or form body
     * @param string $name
     * @return mixed
     */
    private function input(string $name): mixed
    {
        return $this->request->getJsonVar($name) ?? $this->request->getPost($name);
    }
}
