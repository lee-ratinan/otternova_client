<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Only signed-in clients may reach the routes this filter guards.
 * Pages redirect to the login form; AJAX and other non-GET requests get a 401 JSON.
 * A client with Terms of Service or Privacy Policy versions still to accept is held at admin/legal/accept: pages
 * redirect there and other requests get a 403 JSON. Then a client active in no business yet is sent to
 * admin/create-business from every other page.
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('auth');
        /** @var IncomingRequest $request */
        if (hasActiveSession()) {
            if (legalPending() !== [] && ! url_is('admin/legal/accept')) {
                if ($request->getMethod() === 'GET' && ! $request->isAJAX()) {
                    return redirect()->to(base_url('admin/legal/accept'));
                }
                return service('response')->setStatusCode(403)->setJSON([
                    'status'   => 'error',
                    'message'  => lang('Legal.accept.required'),
                    'redirect' => base_url('admin/legal/accept'),
                ]);
            }
            if (needsBusiness() && $request->getMethod() === 'GET' && ! $request->isAJAX() && ! url_is('admin/create-business') && ! url_is('admin/api/*')) {
                return redirect()->to(base_url('admin/create-business'));
            }
            return null;
        }

        if ($request->isAJAX() || $request->getMethod() !== 'GET') {
            return service('response')->setStatusCode(401)->setJSON([
                'status'   => 'error',
                'message'  => lang('Auth.sessionExpired'),
                'redirect' => base_url(),
            ]);
        }

        return redirect()->to(base_url());
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
