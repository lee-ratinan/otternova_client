<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * The opening hours of a store of the session business: its regular weekly hours, and the dates it closes or opens
 * at other times. Its owners and managers only (the routes' manager filter, and v1/client/store/hours* in the API).
 * A store comes in by its public_id. Times are HH:MM in the store's timezone; a closing time not after the opening
 * time is on the next day (10:00-02:00).
 */
class StoreHours extends BaseController
{
    /** Hidden field of the weekly form: its editor's openings, as JSON */
    private const WEEKLY_FIELD = 'weekly_json';

    /** Hidden field of the special-hours form: its editor's openings, as JSON */
    private const HOURS_FIELD = 'hours_json';

    /** exception_kind of the special-hours form */
    private const KIND_CLOSED = 'closed';
    private const KIND_OPEN   = 'open';

    /**
     * The page of a store's hours: the weekly hours (a form with an editor of each day's openings), the special
     * hours from today on (a table; each row can be edited or removed), and the form to set a date's special hours
     * @param string $publicId business_store.public_id
     */
    public function index(string $publicId): string
    {
        $store = $this->findStore($publicId);
        if (null === $store) {
            throw PageNotFoundException::forPageNotFound(lang('Stores.form.not-found'));
        }
        [$code, $body] = $this->callApi('v1/client/store/hours', ['public_id' => $publicId]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException((string) ($body['message'] ?? lang('StoreHours.load-failed')));
        }
        $hours  = (array) $body['data'];
        $locale = $this->request->getLocale();
        $name   = self::storeName($store, $locale);
        $editor = static fn (string $kind, string $field): array => ['type' => 'html', 'html' => view('stores/openings_editor', ['kind' => $kind, 'field' => $field])];
        $urlId  = rawurlencode($publicId);

        return view('generic_form', [
            'locale'       => $locale,
            'slug'         => 'stores',
            'page_title'   => lang('StoreHours.page-title', [$name]),
            'breadcrumbs'  => [
                base_url('admin/business/stores')                => lang('System.page.stores'),
                base_url('admin/business/stores/edit/' . $urlId) => $name,
            ],
            'user'         => (array) session('user'),
            'formConfig'   => [
                'form_id'   => 'weekly-hours-form',
                'title'     => lang('StoreHours.weekly.title'),
                'subtitle'  => lang('StoreHours.weekly.subtitle', [(string) ($hours['store_timezone'] ?? '')]),
                'mode'      => 'edit',
                'id_field'  => 'public_id',
                'id'        => $publicId,
                'locale'    => $locale,
                'urls'      => ['submit' => base_url('admin/business/stores/hours/save')],
                'messages'  => ['saved' => lang('StoreHours.weekly.saved'), 'saveFailed' => lang('StoreHours.save-failed')],
                'fields'    => [$editor('weekly', self::WEEKLY_FIELD)],
            ],
            'extraForms'   => [[
                'form_id'   => 'special-hours-form',
                'title'     => lang('StoreHours.special.title'),
                'subtitle'  => lang('StoreHours.special.subtitle'),
                'mode'      => 'create',
                'id_field'  => 'public_id',
                'id'        => $publicId,
                'id_prefix' => 'special-',
                'locale'    => $locale,
                'urls'      => ['submit' => base_url('admin/business/stores/hours/exception-save')],
                'labels'    => ['save' => lang('StoreHours.special.submit')],
                'messages'  => ['saved' => lang('StoreHours.special.saved'), 'saveFailed' => lang('StoreHours.save-failed')],
                'fields'    => [
                    ['name' => 'exception_date', 'type' => 'date', 'label' => lang('StoreHours.special.date'), 'rules' => 'required',
                        'min' => (string) ($hours['today'] ?? ''), 'help' => lang('StoreHours.special.date-help')],
                    ['name' => 'exception_reason', 'type' => 'text', 'label' => lang('StoreHours.special.reason'), 'rules' => 'required|max_length[255]',
                        'placeholder' => lang('StoreHours.special.reason-placeholder')],
                    ['name' => 'exception_kind', 'type' => 'radio', 'label' => lang('StoreHours.special.kind'), 'inline' => true,
                        'options' => [self::KIND_CLOSED => lang('StoreHours.special.closed'), self::KIND_OPEN => lang('StoreHours.special.open')],
                        'default' => self::KIND_CLOSED, 'rules' => 'required'],
                    $editor('special', self::HOURS_FIELD),
                ],
            ]],
            'extraHtml'    => view('stores/hours_exceptions', ['exceptions' => (array) ($hours['exceptions'] ?? []), 'locale' => $locale]),
            'customScript' => view('scripts/store_hours', [
                'publicId'    => $publicId,
                'weekly'      => (array) ($hours['weekly'] ?? []),
                'exceptions'  => (array) ($hours['exceptions'] ?? []),
                'maxOpenings' => (int) ($hours['max_openings'] ?? 6),
                'fields'      => ['weekly' => self::WEEKLY_FIELD, 'special' => self::HOURS_FIELD],
                'kinds'       => ['closed' => self::KIND_CLOSED, 'open' => self::KIND_OPEN],
                'deleteUrl'   => base_url('admin/business/stores/hours/exception-delete'),
            ]),
        ]);
    }

    /**
     * Save the weekly hours through LINK_INTERNAL_API v1/client/store/hours/save { public_id, weekly }
     */
    public function save(): ResponseInterface
    {
        $post     = (array) $this->request->getPost();
        $publicId = trim((string) ($post['public_id'] ?? ''));
        $weekly   = json_decode((string) ($post[self::WEEKLY_FIELD] ?? ''), true);
        if ('' === $publicId || ! is_array($weekly) || ! array_is_list($weekly)) {
            return $this->saveFailed(422, ['message' => lang('StoreHours.invalid-hours')], lang('StoreHours.save-failed'));
        }
        [$code, $body] = $this->callApi('v1/client/store/hours/save', ['public_id' => $publicId, 'weekly' => $weekly]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $this->editorErrors($body, 'weekly', self::WEEKLY_FIELD), lang('StoreHours.save-failed'));
        }
        return $this->saved($body, $publicId, lang('StoreHours.weekly.saved'));
    }

    /**
     * Set a date's special hours through LINK_INTERNAL_API v1/client/store/hours-exception/save
     * { public_id, exception_date, exception_reason, hours }: none when the form says closed all day
     */
    public function exceptionSave(): ResponseInterface
    {
        $post     = (array) $this->request->getPost();
        $publicId = trim((string) ($post['public_id'] ?? ''));
        $hours    = json_decode((string) ($post[self::HOURS_FIELD] ?? '[]'), true);
        if ('' === $publicId || ! is_array($hours) || ! array_is_list($hours)) {
            return $this->saveFailed(422, ['message' => lang('StoreHours.invalid-hours')], lang('StoreHours.save-failed'));
        }
        $closed = self::KIND_OPEN !== ($post['exception_kind'] ?? self::KIND_CLOSED);
        if (! $closed && [] === $hours) {
            return $this->saveFailed(422, ['message' => lang('StoreHours.special.needs-opening'), 'errors' => [self::HOURS_FIELD => lang('StoreHours.special.needs-opening')]],
                lang('StoreHours.save-failed'));
        }
        [$code, $body] = $this->callApi('v1/client/store/hours-exception/save', [
            'public_id'        => $publicId,
            'exception_date'   => trim((string) ($post['exception_date'] ?? '')),
            'exception_reason' => trim((string) ($post['exception_reason'] ?? '')),
            'hours'            => $closed ? [] : $hours,
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $this->editorErrors($body, 'hours', self::HOURS_FIELD), lang('StoreHours.save-failed'));
        }
        return $this->saved($body, $publicId, lang('StoreHours.special.saved'));
    }

    /**
     * Give a date back its weekly hours through LINK_INTERNAL_API v1/client/store/hours-exception/delete
     * { public_id, exception_date }; the page's script posts it as JSON
     */
    public function exceptionDelete(): ResponseInterface
    {
        $input = (array) ($this->request->getJSON(true) ?? []);
        [$code, $body] = $this->callApi('v1/client/store/hours-exception/delete', [
            'public_id'      => trim((string) ($input['public_id'] ?? '')),
            'exception_date' => trim((string) ($input['exception_date'] ?? '')),
        ]);
        $ok = $code === 200 && ($body['status'] ?? '') === 'success';
        return $this->response->setStatusCode($ok ? 200 : ($code === 200 ? 502 : $code))->setJSON([
            'success'   => $ok,
            'message'   => $body['message'] ?? lang($ok ? 'StoreHours.special.deleted' : 'StoreHours.save-failed'),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * A failed API body with the errors of the editor's openings (weekly.0.open_time, hours.1.close_time, or the
     * list itself) put on its hidden field, so they show there and not as unknown fields
     * @param array  $body   decoded API response
     * @param string $prefix the API's name of the list: weekly, hours
     * @param string $field  the editor's hidden field
     */
    private function editorErrors(array $body, string $prefix, string $field): array
    {
        $errors = [];
        foreach ((array) ($body['errors'] ?? []) as $key => $message) {
            $key = (string) $key;
            if ($key === $prefix || str_starts_with($key, $prefix . '.')) {
                // the first of the editor's errors is enough to point at it; each row's are alike
                $errors[$field] ??= $message;
            } else {
                $errors[$key] = $message;
            }
        }
        $body['errors'] = $errors;
        return $body;
    }

    /**
     * The form's answer to a successful save: back to the store's hours page, so the special hours table shows what
     * was saved too (a create-mode form would only be reset)
     */
    private function saved(array $body, string $publicId, string $fallback): ResponseInterface
    {
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? $fallback,
            'redirect'  => base_url('admin/business/stores/hours/' . rawurlencode($publicId)),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * One store of the session business by its public_id, from LINK_INTERNAL_API v1/client/stores
     * @return array|null null when the business has no such store
     * @throws RuntimeException when the stores cannot be loaded
     */
    private function findStore(string $publicId): ?array
    {
        [$code, $body] = $this->callApi('v1/client/stores', ['business_slug' => (string) currentBusinessSlug()]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException((string) ($body['message'] ?? lang('Stores.list.load-failed')));
        }
        foreach ((array) ($body['data']['stores'] ?? []) as $store) {
            if ((string) ($store['public_id'] ?? '') === $publicId) {
                return (array) $store;
            }
        }
        return null;
    }
}
