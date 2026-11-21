<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * The session business's social media accounts: at most one per platform, each a link to the account on the
 * platform's own site, in the order they are shown. Its owners and managers only (the routes' manager filter, and
 * v1/client/social-links* in the API). The list is edited in place by scripts/social_media and saved as a whole.
 */
class SocialMedia extends BaseController
{
    /** An example address of each platform's accounts, shown as the placeholder of its field */
    public const EXAMPLES = [
        'facebook'  => 'https://www.facebook.com/yourpage',
        'instagram' => 'https://www.instagram.com/yourname',
        'twitter-x' => 'https://x.com/yourname',
        'linkedin'  => 'https://www.linkedin.com/company/yourcompany',
        'youtube'   => 'https://www.youtube.com/@yourchannel',
        'line'      => 'https://lin.ee/yourid',
        'tiktok'    => 'https://www.tiktok.com/@yourname',
    ];

    /**
     * The page: the links the business has, and the platforms it may add, from LINK_INTERNAL_API v1/client/social-links
     */
    public function index(): string
    {
        [$code, $body] = $this->callApi('v1/client/social-links', ['business_slug' => (string) currentBusinessSlug()]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException((string) ($body['message'] ?? lang('SocialMedia.load-failed')));
        }

        return view('social_media/index', [
            'locale'      => $this->request->getLocale(),
            'slug'        => 'social-media',
            'page_title'  => lang('System.page.social-media'),
            'breadcrumbs' => [base_url('admin/business') => lang('System.page.business')],
            'user'        => (array) session('user'),
            'links'       => array_values((array) ($body['data']['links'] ?? [])),
            'platforms'   => (array) ($body['data']['options']['platforms'] ?? []),
            'urls'        => ['save' => base_url('admin/business/social-media/save')],
        ]);
    }

    /**
     * Save the links through LINK_INTERNAL_API v1/client/social-links/save; the page's script posts them as JSON
     * { links: [{platform, profile_url}, ...] } in the order shown, which replace the ones the business had
     * @return ResponseInterface { success, message, links } or { success: false, message, errors } with the API's
     *                           field names (links.1), which the script shows by the row
     */
    public function save(): ResponseInterface
    {
        $input = (array) ($this->request->getJSON(true) ?? []);
        $links = [];
        foreach (is_array($input['links'] ?? null) ? array_values($input['links']) : [] as $link) {
            $links[] = ['platform' => trim((string) ($link['platform'] ?? '')), 'profile_url' => trim((string) ($link['profile_url'] ?? ''))];
        }
        [$code, $body] = $this->callApi('v1/client/social-links/save', ['business_slug' => (string) currentBusinessSlug(), 'links' => $links]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->response->setStatusCode($code === 200 ? 502 : $code)->setJSON([
                'success'   => false,
                'message'   => $body['message'] ?? lang('SocialMedia.save-failed'),
                'errors'    => ((array) ($body['errors'] ?? [])) ?: null,
                'csrf_hash' => csrf_hash(),
            ]);
        }

        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('SocialMedia.saved'),
            'links'     => array_values((array) ($body['data']['links'] ?? [])),
            'csrf_hash' => csrf_hash(),
        ]);
    }
}
