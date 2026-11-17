<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use CURLFile;
use RuntimeException;

/**
 * The pages the session business designs itself (LINK_INTERNAL_API v1/client/webpage*): the list of pages, and the
 * editor of one page with its sections, their texts and pictures. Its owners and managers only (the routes' manager
 * filter, and the API checks again). Every rule (the plan's caps, unique names, which design takes what) is the API's;
 * this controller only passes the requests on and answers the pages' scripts with JSON
 * { success, message, data, errors, csrf_hash }, errors by the API's field names (names.0.page_name, texts.th.headline).
 * The storefront (LINK_MART) draws the pages; the design previews and the page previews are its pages, opened here.
 */
class Webpages extends BaseController
{
    /**
     * The list of pages: create, publish, preview, reorder and delete them (scripts/webpages)
     */
    public function index(): string
    {
        $data = $this->load('v1/client/webpages', []);

        return view('webpages/index', [
            'locale'      => $this->request->getLocale(),
            'slug'        => 'webpages',
            'page_title'  => lang('Webpages.page-title'),
            'breadcrumbs' => [base_url('admin/business') => lang('System.page.business')],
            'user'        => (array) session('user'),
            'pages'       => (array) ($data['pages'] ?? []),
            'options'     => (array) ($data['options'] ?? []),
            'urls'        => $this->urls(),
        ]);
    }

    /**
     * The editor of one page: its names, address and status, its sections' designs and texts, and their pictures
     * (scripts/webpage_editor)
     * @param string $publicId The page's public_id
     */
    public function edit(string $publicId): string
    {
        [$code, $body] = $this->callApi('v1/client/webpage', ['business_slug' => (string) currentBusinessSlug(), 'page_public_id' => $publicId]);
        if ($code === 404 || $code === 400) {
            throw PageNotFoundException::forPageNotFound();
        }
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException((string) ($body['message'] ?? lang('Webpages.load-failed')));
        }
        $list = $this->load('v1/client/webpages', []);

        return view('webpages/edit', [
            'locale'      => $this->request->getLocale(),
            'slug'        => 'webpages',
            'page_title'  => lang('Webpages.editor.title'),
            'breadcrumbs' => [base_url('admin/business') => lang('System.page.business'), base_url('admin/business/webpages') => lang('Webpages.page-title')],
            'user'        => (array) session('user'),
            'page'        => (array) ($body['data'] ?? []),
            'options'     => (array) ($list['options'] ?? []),
            'urls'        => $this->urls(),
        ]);
    }

    /** POST { names?, page_slug? } → v1/client/webpage/create; the first page is always the home page */
    public function create(): ResponseInterface
    {
        return $this->relay('v1/client/webpage/create', ['names', 'page_slug'], 'Webpages.save-failed');
    }

    /** POST { page_public_id, names?, page_slug?, page_status? } → v1/client/webpage/edit */
    public function save(): ResponseInterface
    {
        return $this->relay('v1/client/webpage/edit', ['page_public_id', 'names', 'page_slug', 'page_status'], 'Webpages.save-failed');
    }

    /** POST { page_public_id } → v1/client/webpage/delete */
    public function delete(): ResponseInterface
    {
        return $this->relay('v1/client/webpage/delete', ['page_public_id'], 'Webpages.delete-failed');
    }

    /** POST { order: [public_id] } → v1/client/webpage/reorder, the home page first */
    public function reorder(): ResponseInterface
    {
        return $this->relay('v1/client/webpage/reorder', ['order'], 'Webpages.save-failed');
    }

    /**
     * POST { page_public_id } → v1/client/webpage/preview
     * @return ResponseInterface data { url: the storefront's preview link, expires_at }
     */
    public function preview(): ResponseInterface
    {
        $response = $this->relay('v1/client/webpage/preview', ['page_public_id'], 'Webpages.preview-failed');
        $json     = json_decode((string) $response->getBody(), true);
        if (! ($json['success'] ?? false)) {
            return $response;
        }
        $data = (array) $json['data'];
        $url  = $this->martUrl((string) ($data['path'] ?? '')) . '?preview=' . rawurlencode((string) ($data['token'] ?? ''));

        return $this->response->setJSON(['data' => ['url' => $url, 'expires_at' => $data['expires_at'] ?? null]] + $json);
    }

    /** POST { page_public_id, section_public_id?, design_code, texts } → v1/client/webpage/section/save */
    public function sectionSave(): ResponseInterface
    {
        return $this->relay('v1/client/webpage/section/save', ['page_public_id', 'section_public_id', 'design_code', 'texts'], 'Webpages.save-failed');
    }

    /** POST { page_public_id, section_public_id } → v1/client/webpage/section/delete */
    public function sectionDelete(): ResponseInterface
    {
        return $this->relay('v1/client/webpage/section/delete', ['page_public_id', 'section_public_id'], 'Webpages.delete-failed');
    }

    /** POST multipart { page_public_id, section_public_id, slot_code, image } → v1/client/webpage/image-upload */
    public function imageUpload(): ResponseInterface
    {
        $file = $this->request->getFile('image');
        if (null === $file || ! $file->isValid()) {
            $tooLarge = null !== $file && in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);

            return $this->failed($tooLarge ? 413 : 422, ['message' => lang($tooLarge ? 'Webpages.images.too-large' : 'Webpages.images.choose')], 'Webpages.images.failed');
        }
        $fields = ['business_slug' => (string) currentBusinessSlug()];
        foreach (['page_public_id', 'section_public_id', 'slot_code'] as $field) {
            $fields[$field] = (string) $this->request->getPost($field);
        }
        $fields['image'] = new CURLFile($file->getTempName(), $file->getMimeType(), $file->getClientName());
        [$code, $body]   = $this->callApi('v1/client/webpage/image-upload', $fields, true);

        return $this->answer($code, $body, 'Webpages.images.failed');
    }

    /** POST { public_id } → v1/client/webpage/image-delete */
    public function imageDelete(): ResponseInterface
    {
        return $this->relay('v1/client/webpage/image-delete', ['public_id'], 'Webpages.images.failed');
    }

    /**
     * The data of an API page the screen needs, or the error page
     * @param string $endpoint
     * @param array  $payload
     * @return array
     */
    private function load(string $endpoint, array $payload): array
    {
        [$code, $body] = $this->callApi($endpoint, ['business_slug' => (string) currentBusinessSlug()] + $payload);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException((string) ($body['message'] ?? lang('Webpages.load-failed')));
        }

        return (array) ($body['data'] ?? []);
    }

    /**
     * Pass the script's JSON fields on to the API, for the session business
     * @param string       $endpoint
     * @param list<string> $fields   The ones passed on; a field the script left out stays out
     * @param string       $fallback Language key of the message when the API gives none
     */
    private function relay(string $endpoint, array $fields, string $fallback): ResponseInterface
    {
        $input   = (array) ($this->request->getJSON(true) ?? []);
        $payload = ['business_slug' => (string) currentBusinessSlug()] + array_intersect_key($input, array_flip($fields));
        [$code, $body] = $this->callApi($endpoint, $payload);

        return $this->answer($code, $body, $fallback);
    }

    /** The script's answer to an API call */
    private function answer(int $code, array $body, string $fallback): ResponseInterface
    {
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->failed($code, $body, $fallback, (array) ($body['errors'] ?? []));
        }

        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? null,
            'data'      => $body['data'] ?? null,
            'csrf_hash' => csrf_hash(),
        ]);
    }

    private function failed(int $code, array $body, string $fallback, array $errors = []): ResponseInterface
    {
        return $this->response->setStatusCode($code === 200 ? 502 : $code)->setJSON([
            'success'   => false,
            'message'   => $body['message'] ?? lang($fallback),
            'errors'    => $errors ?: null,
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /** A page of the session business on the storefront: '' its home page, pages/{slug} another */
    private function martUrl(string $path): string
    {
        return rtrim((string) getenv('LINK_MART'), '/') . '/' . rawurlencode((string) currentBusinessSlug()) . ($path === '' ? '' : '/' . $path);
    }

    /** Where the pages' scripts send their requests, and the storefront's addresses they show */
    private function urls(): array
    {
        return [
            'list'          => base_url('admin/business/webpages'),
            'edit'          => base_url('admin/business/webpages/edit/'), // + public_id
            'create'        => base_url('admin/business/webpages/create'),
            'save'          => base_url('admin/business/webpages/save'),
            'delete'        => base_url('admin/business/webpages/delete'),
            'reorder'       => base_url('admin/business/webpages/reorder'),
            'preview'       => base_url('admin/business/webpages/preview'),
            'sectionSave'   => base_url('admin/business/webpages/section-save'),
            'sectionDelete' => base_url('admin/business/webpages/section-delete'),
            'imageUpload'   => base_url('admin/business/webpages/image-upload'),
            'imageDelete'   => base_url('admin/business/webpages/image-delete'),
            'website'       => base_url('admin/business/website'),
            'plans'         => base_url('admin/plans'),
            'mart'          => $this->martUrl('') . '/', // + path
            'designPreview' => $this->martUrl('design-preview') . '/', // + design_code
            'thumbnails'    => rtrim((string) getenv('LINK_ASSETS'), '/') . '/img/sections/', // + design_code.webp
        ];
    }
}
