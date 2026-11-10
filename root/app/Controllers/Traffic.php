<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * The session business's traffic on the marketplace: who visited its pages, from where, on what, and how many of
 * them went on to order (the conversion rate). Read through LINK_INTERNAL_API v1/client/traffic/*, which lets the
 * business's active owners and managers only, as does the route's filter.
 * The page itself is a shell: its script (assets/js/traffic.js) loads each card through admin/traffic/data/*,
 * which always asks for the session business, whatever the browser sends, so the page shows no other business's
 * data. ?from=&to= (YYYY-MM-DD) set the period, so a view can be linked to.
 */
class Traffic extends BaseController
{
    /** The API endpoint behind each data route, and the fields of the request it takes from the browser */
    private const DATA = [
        'overview'  => ['from', 'to'],
        'breakdown' => ['from', 'to', 'dimension', 'limit'],
        'visits'    => ['from', 'to', 'start', 'length'],
    ];

    /**
     * admin/traffic
     * @return string|RedirectResponse
     */
    public function index(): string|RedirectResponse
    {
        if (null === currentBusinessSlug()) {
            return redirect()->to(base_url('admin/dashboard'));
        }
        $get  = fn (string $name): string => trim((string) $this->request->getGet($name));
        $date = static fn (string $value): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';

        return view('admin_traffic', [
            'locale' => $this->request->getLocale(),
            'slug'   => 'traffic',
            'user'   => session()->get('user') ?? [],
            'from'   => $date($get('from')),
            'to'     => $date($get('to')),
        ]);
    }

    /**
     * POST admin/traffic/data/{overview|breakdown|visits}: the API's answer as it is, { status, message?, data? },
     * with the API's HTTP status
     * @param string $endpoint
     * @return ResponseInterface
     */
    public function data(string $endpoint): ResponseInterface
    {
        $slug = currentBusinessSlug();
        if (null === $slug) {
            return $this->response->setStatusCode(403)->setJSON([
                'status'    => 'error',
                'message'   => lang('Traffic.no-business'),
                'csrf_hash' => csrf_hash(),
            ]);
        }
        $input   = $this->request->getJSON(true);
        $input   = is_array($input) ? $input : [];
        $payload = array_intersect_key($input, array_flip(self::DATA[$endpoint]));
        // set last, so the browser cannot ask for another business
        $payload['business_slug'] = $slug;
        [$code, $body] = $this->callApi('v1/client/traffic/' . $endpoint, $payload);
        if (! isset($body['status'])) {
            $body['status'] = 'error';
        }

        return $this->response->setStatusCode($code)->setJSON($body + ['csrf_hash' => csrf_hash()]);
    }
}
