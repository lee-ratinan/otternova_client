<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Only signed-in clients may reach the routes this filter guards.
 * Pages redirect to the login form; AJAX and other non-GET requests get a 401 JSON.
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('auth');
        if (hasActiveSession()) {
            return null;
        }

        /** @var IncomingRequest $request */
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
