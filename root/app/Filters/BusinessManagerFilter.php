<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Only the owners (O) and managers (M) of the session business may reach the routes this filter guards
 * (admin/business/*); with the argument "owner" ('manager:owner'), its owners only (admin/plans). Runs after
 * AuthFilter, so the client is signed in.
 * Pages redirect to the dashboard; AJAX and other non-GET requests get a 403 JSON.
 */
class BusinessManagerFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('auth');
        $ownersOnly = in_array('owner', (array) $arguments, true);
        if ($ownersOnly ? isBusinessOwner() : canManageBusiness()) {
            return null;
        }

        /** @var IncomingRequest $request */
        if ($request->isAJAX() || $request->getMethod() !== 'GET') {
            return service('response')->setStatusCode(403)->setJSON([
                'success'   => false,
                'message'   => lang($ownersOnly ? 'BusinessPlans.owners-only' : 'EditBusiness.managers-only'),
                'csrf_hash' => csrf_hash(),
            ]);
        }

        return redirect()->to(base_url('admin/dashboard'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
