<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * The sessions of the session business's group services: the list, and the form of one with its slots and whom and
 * what each slot holds. Its owners and managers only (see BusinessManagerFilter), as LINK_INTERNAL_API
 * v1/client/session* allows.
 *
 * Times are entered and shown in the store's own timezone (the API's local_ fields), as a class in Bangkok is at
 * 11:00 Bangkok time wherever the manager is.
 */
class ServiceSessions extends BaseController
{
    private const STATUS_DRAFT     = 'D';
    private const STATUS_CANCELLED = 'X';
    private const STATUSES         = ['D', 'O', 'C', self::STATUS_CANCELLED];
    private const PERIODS          = ['upcoming', 'past', 'all'];

    /** The fields of the form that go to the API as "session"; the variant and the store on a create only */
    private const FIELDS = ['session_name', 'session_description', 'capacity', 'booking_closes_at', 'session_status'];

    /** Form field names of the name and description in a language: translation_name_<language_code> */
    private const NAME_PREFIX        = 'translation_name_';
    private const DESCRIPTION_PREFIX = 'translation_description_';

    /** Hidden field of the form holding the slots editor's slots, as JSON */
    private const SLOTS_FIELD = 'slots_json';

    private const FORM_ID = 'session-form';

    /**
     * The sessions (generic_list), upcoming ones by default; its filter shows past ones, or all
     */
    public function index(): string
    {
        return view('generic_list', [
            'locale'      => $this->request->getLocale(),
            'slug'        => 'sessions',
            'breadcrumbs' => [base_url('admin/artifacts') => lang('System.page.products-and-services')],
            'user'        => (array) session('user'),
            'listConfig'  => $this->listConfig(),
        ]);
    }

    /**
     * DataTables data of index(), from v1/client/sessions
     */
    public function data(): ResponseInterface
    {
        $config = $this->listConfig();
        $params = lb_request($this->request, $config);
        $period = 'upcoming';
        foreach ($params['filters'] as $filter) {
            if ('period' === $filter['def']['name'] && in_array($filter['value'], self::PERIODS, true)) {
                $period = $filter['value'];
            }
        }
        [$code, $body] = $this->callApi('v1/client/sessions', ['business_slug' => (string) currentBusinessSlug(), 'period' => $period]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, [])
                + ['error' => (string) ($body['message'] ?? lang('ServiceSessions.list.load-failed')), 'csrf_hash' => csrf_hash()]);
        }
        $rows = [];
        foreach ((array) ($body['data']['sessions'] ?? []) as $session) {
            $rows[] = [
                'public_id'      => (string) ($session['public_id'] ?? ''),
                'session_name'   => (string) ($session['session_name'] ?? ''),
                'service'        => (string) ($session['artifact']['artifact_name'] ?? ''),
                'store_name'     => (string) ($session['store']['store_name'] ?? ''),
                'starts'         => self::shown($session['local_starts_at'] ?? null),
                'slot_count'     => (int) ($session['slot_count'] ?? 0),
                'seats'          => (int) ($session['seats_booked'] ?? 0) . ' / ' . (int) ($session['capacity'] ?? 0),
                'session_status' => (string) ($session['session_status'] ?? ''),
            ];
        }
        $total    = count($rows);
        $statuses = lang('ServiceSessions.enum.status');
        $rows     = $this->searchAndOrder($rows, $params, static fn (array $row, string $column) => 'session_status' === $column
            ? (string) ($statuses[$row[$column]] ?? $row[$column])
            : (string) $row[$column]);
        $filtered = count($rows);
        $rows     = lb_rows(array_slice($rows, $params['start'], $params['length']), $config);

        return $this->response->setJSON(lb_response($params['draw'], $total, $filtered, $rows) + ['csrf_hash' => csrf_hash()]);
    }

    /**
     * The form to add a session, or, with $publicId, one opened in view mode. Its variant and store are picked when it
     * is added and cannot change.
     * @param string|null $publicId service_session.public_id
     */
    public function form(?string $publicId = null): string
    {
        $locale = $this->request->getLocale();
        $values = [];
        $slots  = [];
        $place  = [];
        if (null !== $publicId) {
            [$code, $body] = $this->callApi('v1/client/session', ['public_id' => $publicId]);
            if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
                throw PageNotFoundException::forPageNotFound(lang('ServiceSessions.form.not-found'));
            }
            $session = (array) $body['data']['session'];
            $slots   = (array) ($body['data']['slots'] ?? []);
            $values  = [
                'public_id'           => (string) $session['public_id'],
                'variant_public_id'   => (string) ($session['variant']['public_id'] ?? ''),
                'store_public_id'     => (string) ($session['store']['public_id'] ?? ''),
                'session_name'        => (string) $session['session_name'],
                'session_description' => (string) ($session['session_description'] ?? ''),
                'capacity'            => (int) $session['capacity'],
                'booking_closes_at'   => (string) ($session['local_booking_closes_at'] ?? ''),
                'session_status'      => (string) $session['session_status'],
                'seats_booked'        => (int) ($session['seats_booked'] ?? 0),
            ];
            foreach ((array) ($session['translations'] ?? []) as $language => $texts) {
                $values[self::NAME_PREFIX . $language]        = $texts['session_name'] ?? '';
                $values[self::DESCRIPTION_PREFIX . $language] = $texts['session_description'] ?? '';
            }
            $place = ['variant_public_id' => $values['variant_public_id'], 'store_public_id' => $values['store_public_id']];
        }
        $options  = $this->loadOptions($place);
        $variants = $this->variantOptions((array) ($options['variants'] ?? []));
        $stores   = [];
        foreach ((array) ($options['stores'] ?? []) as $store) {
            $stores[(string) $store['public_id']] = (string) $store['store_name'];
        }
        $editor = [
            'variants'  => array_values((array) ($options['variants'] ?? [])),
            'staff'     => (array) ($options['staff'] ?? []),
            'resources' => (array) ($options['resources'] ?? []),
            'needs'     => (array) ($options['needs'] ?? []),
            'slots'     => $slots,
        ];
        $cancelled = self::STATUS_CANCELLED === ($values['session_status'] ?? '');

        return view('generic_form', [
            'locale'       => $locale,
            'slug'         => 'sessions',
            'page_title'   => $values['session_name'] ?? lang('System.page.session-create'),
            'breadcrumbs'  => [base_url('admin/sessions') => lang('System.page.sessions')],
            'user'         => (array) session('user'),
            'formConfig'   => [
                'form_id'  => self::FORM_ID,
                'title'    => lang(null === $publicId ? 'ServiceSessions.form.create-title' : 'ServiceSessions.form.edit-title'),
                'subtitle' => lang('ServiceSessions.form.subtitle', [(string) currentBusinessName()]),
                'mode'     => null === $publicId ? 'create' : 'view',
                'id_field' => 'public_id',
                'id'       => (string) $publicId,
                'locale'   => $locale,
                'urls'     => [
                    'submit' => base_url('admin/sessions/save'),
                    // a cancelled session cannot change any more
                    'edit'   => null === $publicId || $cancelled ? '' : base_url('admin/sessions/edit/' . rawurlencode($publicId)),
                    'list'   => base_url('admin/sessions'),
                ],
                'messages' => ['saved' => lang(null === $publicId ? 'ServiceSessions.form.created' : 'ServiceSessions.form.saved'),
                    'saveFailed' => lang('ServiceSessions.form.failed')],
                'values'   => $values,
                'fields'   => $this->fields($variants, $stores, $editor, null !== $publicId),
            ],
            'customScript' => view('scripts/session_form', [
                'formId'     => self::FORM_ID,
                'slotsField' => self::SLOTS_FIELD,
                'editor'     => $editor,
                'urls'       => ['options' => base_url('admin/sessions/options')],
            ]),
        ]);
    }

    /**
     * What a slot of the variant at the store may hold, for the slots editor when either is picked: v1/client/session/options
     * @return ResponseInterface { success, staff, resources, needs }
     */
    public function options(): ResponseInterface
    {
        $place = array_filter([
            'variant_public_id' => trim((string) $this->request->getPost('variant_public_id')),
            'store_public_id'   => trim((string) $this->request->getPost('store_public_id')),
        ], 'strlen');
        try {
            $options = $this->loadOptions($place);
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(502)->setJSON(['success' => false, 'message' => $e->getMessage(), 'csrf_hash' => csrf_hash()]);
        }

        return $this->response->setJSON([
            'success'   => true,
            'staff'     => (array) ($options['staff'] ?? []),
            'resources' => (array) ($options['resources'] ?? []),
            'needs'     => (array) ($options['needs'] ?? []),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Create or save a session of the session business through LINK_INTERNAL_API:
     * v1/client/session/create { business_slug, session, translations, slots }, or, with public_id,
     * v1/client/session/edit { public_id, session, translations, slots }. The form has the texts in the web pages' languages
     * only; on an edit, the other languages' are kept, as the API replaces them all. booking_closes_at left empty on a
     * new session is not sent, so the API sets it from the booking window's notice; emptied on an edit, it is null
     * (booking stays open until the first slot starts).
     */
    public function save(): ResponseInterface
    {
        $post     = (array) $this->request->getPost();
        $publicId = trim((string) ($post['public_id'] ?? ''));
        $session  = array_intersect_key($post, array_flip(self::FIELDS));
        foreach (['session_description', 'booking_closes_at'] as $field) {
            if (array_key_exists($field, $session) && '' === trim((string) $session[$field])) {
                $session[$field] = null;
            }
        }
        if ('' === $publicId && array_key_exists('booking_closes_at', $session) && null === $session['booking_closes_at']) {
            unset($session['booking_closes_at']);
        }
        if ('' === $publicId) {
            $session['variant_public_id'] = (string) ($post['variant_public_id'] ?? '');
            $session['store_public_id']   = (string) ($post['store_public_id'] ?? '');
        }
        $slots = json_decode((string) ($post[self::SLOTS_FIELD] ?? ''), true);
        if (! is_array($slots) || ! array_is_list($slots)) {
            return $this->saveFailed(422, ['message' => lang('ServiceSessions.form.invalid-slots')], lang('ServiceSessions.form.failed'));
        }
        $slots = array_map(static fn ($slot) => array_intersect_key((array) $slot, array_flip(['slot_number', 'starts_at', 'ends_at', 'slot_status', 'staff', 'resources'])), $slots);

        $names = [];
        if ('' !== $publicId) {
            [$code, $body] = $this->callApi('v1/client/session', ['public_id' => $publicId]);
            if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
                return $this->saveFailed($code, $body, lang('ServiceSessions.form.failed'));
            }
            $names = (array) ($body['data']['session']['translations'] ?? []);
        }
        foreach (array_keys($this->websiteLanguages()) as $language) {
            $names[$language] = [
                'session_name'        => (string) ($post[self::NAME_PREFIX . $language] ?? ''),
                'session_description' => (string) ($post[self::DESCRIPTION_PREFIX . $language] ?? ''),
            ];
        }

        $payload = ['session' => $session, 'translations' => (object) $names, 'slots' => $slots];
        [$code, $body] = '' === $publicId
            ? $this->callApi('v1/client/session/create', ['business_slug' => (string) currentBusinessSlug()] + $payload)
            : $this->callApi('v1/client/session/edit', ['public_id' => $publicId] + $payload);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->sessionFailed($code, $body);
        }
        $savedId = (string) ($body['data']['session']['public_id'] ?? $publicId);
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('' === $publicId ? 'ServiceSessions.form.created' : 'ServiceSessions.form.saved'),
            'redirect'  => base_url('admin/sessions/edit/' . rawurlencode($savedId)),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * v1/client/session/options: the variants and stores, and, for $place's variant and store, what a slot may hold
     * @param array $place variant_public_id, store_public_id
     * @return array
     * @throws RuntimeException when the API cannot be reached
     */
    private function loadOptions(array $place): array
    {
        [$code, $body] = $this->callApi('v1/client/session/options', ['business_slug' => (string) currentBusinessSlug()] + $place);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException((string) ($body['message'] ?? lang('ServiceSessions.form.options-failed')));
        }
        return (array) ($body['data'] ?? []);
    }

    /**
     * The group variants as select options: "Muay Thai · Beginners · 90 min" (a variant with no attribute values is
     * its service's name and length); one off sale or of an inactive service says so
     * @param list<array> $variants
     * @return array<string, string> public_id => label
     */
    private function variantOptions(array $variants): array
    {
        $options = [];
        foreach ($variants as $variant) {
            $label = implode(' · ', array_filter([
                (string) ($variant['artifact']['artifact_name'] ?? ''),
                implode(' / ', (array) ($variant['attribute_values'] ?? [])),
                formatMinutes($variant['duration_minutes'] ?? null, $this->request->getLocale()),
            ]));
            if ('A' !== ($variant['variant_status'] ?? 'A') || 'A' !== ($variant['artifact']['artifact_status'] ?? 'A')) {
                $label .= ' (' . lang('ServiceSessions.form.not-on-sale') . ')';
            }
            $options[(string) $variant['public_id']] = $label;
        }
        return $options;
    }

    /**
     * Fields of the session form
     * @param array<string, string> $variants public_id => label
     * @param array<string, string> $stores   public_id => name
     * @param array                 $editor   the slots editor's data
     * @param bool                  $saved    an existing session: the variant and the store are fixed
     */
    private function fields(array $variants, array $stores, array $editor, bool $saved): array
    {
        $statuses = lang('ServiceSessions.enum.status');
        $fields   = [
            ['type' => 'heading', 'label' => lang('ServiceSessions.heading.session')],
            ['name' => 'variant_public_id', 'type' => 'select', 'label' => lang('ServiceSessions.fields.variant'), 'options' => $variants,
                'rules' => 'required', 'disabled_in' => ['edit'], 'view' => static fn ($v) => $variants[$v] ?? $v,
                'help' => lang([] === $variants ? 'ServiceSessions.help.no-variant' : 'ServiceSessions.help.variant')],
            ['name' => 'store_public_id', 'type' => 'select', 'label' => lang('ServiceSessions.fields.store'), 'options' => $stores,
                'rules' => 'required', 'disabled_in' => ['edit'], 'view' => static fn ($v) => $stores[$v] ?? $v,
                'help' => lang('ServiceSessions.help.store')],
            ['name' => 'session_name', 'type' => 'text', 'label' => lang('ServiceSessions.fields.session_name'), 'rules' => 'required|max_length[255]',
                'help' => lang('ServiceSessions.help.session-name')],
            ['name' => 'session_description', 'type' => 'textarea', 'label' => lang('ServiceSessions.fields.session_description'),
                'rules' => 'max_length[10000]', 'optional' => true, 'rows' => 3],
            ['name' => 'capacity', 'type' => 'number', 'label' => lang('ServiceSessions.fields.capacity'),
                'rules' => 'required|is_natural_no_zero|less_than_equal_to[65535]', 'attrs' => ['min' => 1, 'max' => 65535, 'step' => 1],
                'help' => lang('ServiceSessions.help.capacity')],
            ['name' => 'booking_closes_at', 'type' => 'datetime-local', 'label' => lang('ServiceSessions.fields.booking_closes_at'), 'optional' => true,
                'view' => static fn ($v) => self::shown($v), 'help' => lang('ServiceSessions.help.booking-closes-at')],
            ['name' => 'session_status', 'type' => 'select', 'label' => lang('ServiceSessions.fields.session_status'), 'options' => $statuses,
                'default' => self::STATUS_DRAFT, 'rules' => 'required|in_list[' . implode(',', self::STATUSES) . ']',
                'help' => lang('ServiceSessions.help.status')],
        ];
        if ($saved) {
            $fields[] = ['name' => 'seats_booked', 'type' => 'number', 'label' => lang('ServiceSessions.fields.seats_booked'), 'show_in' => ['view']];
        }
        $fields[] = ['type' => 'heading', 'label' => lang('ServiceSessions.heading.slots')];
        $fields[] = ['type' => 'html', 'show_in' => ['create', 'edit'], 'html' => view('sessions/slots_editor', ['field' => self::SLOTS_FIELD])];
        $fields[] = ['type' => 'html', 'show_in' => ['view'], 'html' => view('sessions/slots_view', ['slots' => $editor['slots'], 'locale' => $this->request->getLocale()])];
        $languages = $this->websiteLanguages();
        if ([] !== $languages) {
            $fields[] = ['type' => 'heading', 'label' => lang('ServiceSessions.heading.translations')];
        }
        foreach ($languages as $language => $label) {
            $fields[] = ['name' => self::NAME_PREFIX . $language, 'type' => 'text', 'label' => lang('ServiceSessions.translation-name', [$label]),
                'rules' => 'max_length[255]', 'optional' => true];
            $fields[] = ['name' => self::DESCRIPTION_PREFIX . $language, 'type' => 'textarea', 'label' => lang('ServiceSessions.translation-description', [$label]),
                'rules' => 'max_length[10000]', 'optional' => true, 'rows' => 3];
        }
        return $fields;
    }

    /**
     * Table Builder config of index(); its period filter picks upcoming (the default), past or all
     */
    private function listConfig(): array
    {
        $periods = [];
        foreach (self::PERIODS as $p) {
            $periods[$p] = lang('ServiceSessions.period.' . $p);
        }
        $statuses = lang('ServiceSessions.enum.status');
        return [
            'table_id' => 'sessions-table',
            'title'    => lang('ServiceSessions.list.title'),
            'subtitle' => lang('ServiceSessions.list.subtitle', [(string) currentBusinessName()]),
            'id_field' => 'public_id',
            'urls'     => [
                'data'   => base_url('admin/sessions/data'),
                'create' => base_url('admin/sessions/create'),
                'edit'   => base_url('admin/sessions/edit/{public_id}'),
            ],
            'labels'   => ['add' => lang('ServiceSessions.list.add')],
            'messages' => ['loadFailed' => lang('ServiceSessions.list.load-failed')],
            'columns'  => [
                ['data' => 'session_name', 'title' => lang('ServiceSessions.fields.session_name')],
                ['data' => 'service', 'title' => lang('ServiceSessions.fields.service')],
                ['data' => 'store_name', 'title' => lang('ServiceSessions.fields.store')],
                ['data' => 'starts', 'title' => lang('ServiceSessions.fields.starts')],
                ['data' => 'slot_count', 'title' => lang('ServiceSessions.fields.slot_count'), 'type' => 'number', 'decimals' => 0, 'class' => 'text-end', 'searchable' => false],
                ['data' => 'seats', 'title' => lang('ServiceSessions.fields.seats'), 'class' => 'text-end', 'searchable' => false],
                ['data' => 'session_status', 'title' => lang('ServiceSessions.fields.session_status'), 'type' => 'badge', 'badges' => [
                    'D' => ['label' => $statuses['D'], 'class' => 'bg-secondary'],
                    'O' => ['label' => $statuses['O'], 'class' => 'bg-success'],
                    'C' => ['label' => $statuses['C'], 'class' => 'bg-warning text-dark'],
                    'X' => ['label' => $statuses['X'], 'class' => 'bg-danger'],
                ]],
            ],
            'filters'  => [
                ['name' => 'period', 'type' => 'select', 'label' => lang('ServiceSessions.period.label'), 'options' => $periods, 'default' => 'upcoming'],
            ],
            'values'   => ['period' => 'upcoming'],
            'order'    => [['starts', 'asc']],
        ];
    }

    /**
     * A local datetime as shown: "2026-11-14 10:00"
     */
    private static function shown(mixed $local): string
    {
        return is_string($local) ? str_replace('T', ' ', $local) : '';
    }

    /**
     * Form response of a failed create or edit: the API names the errors of the texts translations.<language>.<column>,
     * renamed for the form, and those of a slot slots.<index>.<field>, told by the slot's number, as the slots editor
     * is not one of the form's fields
     */
    private function sessionFailed(int $code, array $body): ResponseInterface
    {
        $columns = lang('ServiceSessions.slots.column');
        $errors  = [];
        foreach ((array) ($body['errors'] ?? []) as $key => $message) {
            $key = (string) $key;
            if (preg_match('/^translations\.([^.]+)(?:\.(session_name|session_description))?$/', $key, $m)) {
                $errors[('session_description' === ($m[2] ?? '') ? self::DESCRIPTION_PREFIX : self::NAME_PREFIX) . $m[1]] = $message;
            } elseif (preg_match('/^slots\.(\d+)\.([a-z_]+)/', $key, $m)) {
                $field        = isset($columns[$m[2]]) ? $columns[$m[2]] . ': ' : '';
                $errors[$key] = lang('ServiceSessions.slots.error', [(int) $m[1] + 1, $field . $message]);
            } else {
                $errors[$key] = $message;
            }
        }
        return $this->response->setStatusCode($code === 200 ? 422 : $code)->setJSON([
            'success'   => false,
            'message'   => $body['message'] ?? lang('ServiceSessions.form.failed'),
            'errors'    => $errors ?: null,
            'csrf_hash' => csrf_hash(),
        ]);
    }
}
