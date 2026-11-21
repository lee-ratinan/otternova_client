<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * The tags of the session business, which people search businesses by: in any language, shared with other businesses
 * (a tag written like one that exists is that one), at most the API's max_tags. Its owners and managers only (the
 * routes' manager filter, and v1/client/tags* in the API). The tags are edited in place by scripts/tags, which
 * suggests the tags other businesses use as they are typed, and saved as a whole.
 */
class Tags extends BaseController
{
    /**
     * The page: the business's tags and the limits, from LINK_INTERNAL_API v1/client/tags
     */
    public function index(): string
    {
        [$code, $body] = $this->callApi('v1/client/tags', ['business_slug' => (string) currentBusinessSlug()]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException((string) ($body['message'] ?? lang('Tags.load-failed')));
        }

        return view('tags/index', [
            'locale'      => $this->request->getLocale(),
            'slug'        => 'tags',
            'page_title'  => lang('System.page.tags'),
            'breadcrumbs' => [base_url('admin/business') => lang('System.page.business')],
            'user'        => (array) session('user'),
            'tags'        => array_values(array_map(static fn ($t): string => (string) ($t['tag_name'] ?? ''), (array) ($body['data']['tags'] ?? []))),
            'options'     => (array) ($body['data']['options'] ?? []),
            'urls'        => ['save' => base_url('admin/business/tags/save'), 'suggest' => base_url('admin/business/tags/suggest')],
        ]);
    }

    /**
     * Save the tags through LINK_INTERNAL_API v1/client/tags/save; the page's script posts them as JSON
     * { tags: ["Thai massage", ...] }, which replace the ones the business had
     * @return ResponseInterface { success, message, tags } (the names, as the API keeps them) or { success: false,
     *                           message, errors } with the API's field names (tags.1, tags)
     */
    public function save(): ResponseInterface
    {
        $input = (array) ($this->request->getJSON(true) ?? []);
        $tags  = array_map(static fn ($t): string => trim((string) (is_scalar($t) ? $t : '')), is_array($input['tags'] ?? null) ? array_values($input['tags']) : []);
        [$code, $body] = $this->callApi('v1/client/tags/save', ['business_slug' => (string) currentBusinessSlug(), 'tags' => $tags]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->failed($code, $body, lang('Tags.save-failed'));
        }

        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('Tags.saved'),
            'tags'      => array_values(array_map(static fn ($t): string => (string) ($t['tag_name'] ?? ''), (array) ($body['data']['tags'] ?? []))),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Tags other businesses use that hold what is typed, through LINK_INTERNAL_API v1/client/tags/suggest; the
     * script posts JSON { q }
     * @return ResponseInterface { success, suggestions: [{tag_name, business_count}] }
     */
    public function suggest(): ResponseInterface
    {
        $q = trim((string) (((array) ($this->request->getJSON(true) ?? []))['q'] ?? ''));
        if ($q === '') {
            return $this->response->setJSON(['success' => true, 'suggestions' => [], 'csrf_hash' => csrf_hash()]);
        }
        [$code, $body] = $this->callApi('v1/client/tags/suggest', ['business_slug' => (string) currentBusinessSlug(), 'q' => $q]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->failed($code, $body, lang('Tags.load-failed'));
        }

        return $this->response->setJSON([
            'success'     => true,
            'suggestions' => array_values((array) ($body['data']['suggestions'] ?? [])),
            'csrf_hash'   => csrf_hash(),
        ]);
    }

    /**
     * The script's answer to a failed API call
     * @param int    $code     HTTP status of the API call
     * @param array  $body     decoded API response
     * @param string $fallback message when the API gives none
     */
    private function failed(int $code, array $body, string $fallback): ResponseInterface
    {
        return $this->response->setStatusCode($code === 200 ? 502 : $code)->setJSON([
            'success'   => false,
            'message'   => $body['message'] ?? $fallback,
            'errors'    => ((array) ($body['errors'] ?? [])) ?: null,
            'csrf_hash' => csrf_hash(),
        ]);
    }
}
