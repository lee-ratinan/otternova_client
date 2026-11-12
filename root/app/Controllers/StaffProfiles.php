<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use Locale;
use RuntimeException;

/**
 * One person's profile page in the session business, from LINK_INTERNAL_API v1/client/staff/profile and its saves:
 * the languages they speak, their values of the attributes the business records (StaffAttributes), and the customers
 * they do not serve, which customers are told before booking them. Its owners and managers (the routes' manager
 * filter). A person comes in by their auth_master.uid; the page opens from their Staff page.
 */
class StaffProfiles extends BaseController
{
    /** Hidden fields of the editors, holding their rows as JSON */
    private const LANGUAGES_FIELD = 'languages_json';
    private const VALUES_FIELD    = 'values_json';

    /** proficiency, from the best */
    private const PROFICIENCIES = ['N', 'F', 'C', 'B'];

    /** excluded_genders */
    private const GENDERS = ['M', 'F', 'NB'];

    /**
     * The languages offered in the languages editor, BCP 47: the ones people of the region's businesses most often
     * speak. A saved one not here is offered too.
     */
    private const SPOKEN_LANGUAGES = [
        'th', 'en', 'zh', 'yue', 'nan', 'hak', 'ja', 'ko', 'vi', 'id', 'ms', 'fil', 'my', 'km', 'lo', 'jv', 'su',
        'hi', 'ta', 'bn', 'ur', 'ne', 'si', 'ar', 'fa', 'he', 'tr', 'ru', 'uk', 'pl', 'de', 'fr', 'es', 'pt', 'it',
        'nl', 'sv', 'da', 'no', 'fi', 'el', 'mn',
    ];

    /**
     * The person's profile page: the languages editor, the details editor (their values of each attribute of the
     * business) and the restrictions form, with what customers are told of them
     * @param string $uid auth_master.uid
     */
    public function form(string $uid): string
    {
        [$code, $body] = $this->callApi('v1/client/staff/profile', ['business_slug' => (string) currentBusinessSlug(), 'uid' => $uid]);
        if ($code === 404) {
            throw PageNotFoundException::forPageNotFound((string) ($body['message'] ?? lang('Staff.form.not-found')));
        }
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException((string) ($body['message'] ?? lang('StaffProfiles.load-failed')));
        }
        $data         = (array) $body['data'];
        $name         = (string) $data['person']['full_name'];
        $locale       = $this->request->getLocale();
        $languages    = (array) ($data['languages'] ?? []);
        $attributes   = (array) ($data['attributes'] ?? []);
        $restrictions = (array) ($data['restrictions'] ?? []);
        $hidden       = ['name' => 'uid', 'type' => 'hidden', 'default' => $uid];
        $genders      = [];
        foreach (self::GENDERS as $gender) {
            $genders[$gender] = lang('StaffProfiles.restrictions.gender.' . $gender);
        }
        $age = static fn ($v) => null === $v || '' === $v ? lang('StaffProfiles.restrictions.no-limit') : (string) $v;

        return view('generic_form', [
            'locale'       => $locale,
            'slug'         => 'staff',
            'page_title'   => lang('StaffProfiles.page-title', [$name]),
            'breadcrumbs'  => [
                base_url('admin/staff')                             => lang('System.page.staff'),
                base_url('admin/staff/edit/' . rawurlencode($uid)) => $name,
            ],
            'user'         => (array) session('user'),
            'formConfig'   => [
                'form_id'   => 'staff-languages-form',
                'title'     => lang('StaffProfiles.languages.title'),
                'subtitle'  => lang('StaffProfiles.languages.subtitle', [$name]),
                'mode'      => 'edit',
                'id_prefix' => 'languages-',
                'locale'    => $locale,
                'urls'      => ['submit' => base_url('admin/staff/profile/languages-save')],
                'messages'  => ['saved' => lang('StaffProfiles.languages.saved'), 'saveFailed' => lang('StaffProfiles.save-failed')],
                'values'    => ['uid' => $uid],
                'fields'    => [
                    ['type' => 'html', 'html' => view('staff/languages_editor', ['field' => self::LANGUAGES_FIELD])],
                    $hidden,
                ],
            ],
            'extraForms'   => [
                [
                    'form_id'   => 'staff-details-form',
                    'title'     => lang('StaffProfiles.details.title'),
                    'subtitle'  => lang('StaffProfiles.details.subtitle'),
                    'mode'      => 'edit',
                    'id_prefix' => 'details-',
                    'locale'    => $locale,
                    'urls'      => ['submit' => base_url('admin/staff/profile/details-save')],
                    'messages'  => ['saved' => lang('StaffProfiles.details.saved'), 'saveFailed' => lang('StaffProfiles.save-failed')],
                    'values'    => ['uid' => $uid],
                    'editable'  => [] !== $attributes,
                    'fields'    => [
                        ['type' => 'html', 'html' => view('staff/details_editor', ['field' => self::VALUES_FIELD, 'hasAttributes' => [] !== $attributes,
                            'attributesUrl' => base_url('admin/staff/attributes')])],
                        $hidden,
                    ],
                ],
                [
                    'form_id'   => 'staff-restrictions-form',
                    'title'     => lang('StaffProfiles.restrictions.title'),
                    'subtitle'  => lang('StaffProfiles.restrictions.subtitle'),
                    'mode'      => 'view',
                    'id_prefix' => 'restrictions-',
                    'locale'    => $locale,
                    'urls'      => ['submit' => base_url('admin/staff/profile/restrictions-save')],
                    'messages'  => ['saved' => lang('StaffProfiles.restrictions.saved'), 'saveFailed' => lang('StaffProfiles.save-failed')],
                    'values'    => [
                        'excluded_genders' => (array) ($restrictions['excluded_genders'] ?? []),
                        'age_min'          => $restrictions['age_min'] ?? null,
                        'age_max'          => $restrictions['age_max'] ?? null,
                        'uid'              => $uid,
                    ],
                    'fields'    => [
                        ['type' => 'html', 'show_in' => ['edit'], 'html' => '<div class="alert alert-info small">' . esc(lang('StaffProfiles.restrictions.how')) . '</div>'],
                        ['name' => 'excluded_genders', 'type' => 'checkbox', 'label' => lang('StaffProfiles.restrictions.genders'), 'options' => $genders,
                            'inline' => true, 'optional' => true, 'help' => lang('StaffProfiles.restrictions.genders-help'),
                            'view' => static fn ($v) => [] === (array) $v ? lang('StaffProfiles.restrictions.serves-all')
                                : implode(', ', array_map(static fn ($g) => $genders[$g] ?? $g, (array) $v))],
                        ['name' => 'age_min', 'type' => 'number', 'label' => lang('StaffProfiles.restrictions.age-min'), 'optional' => true, 'min' => 0, 'max' => 120, 'step' => 1,
                            'rules' => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[120]', 'view' => $age],
                        ['name' => 'age_max', 'type' => 'number', 'label' => lang('StaffProfiles.restrictions.age-max'), 'optional' => true, 'min' => 0, 'max' => 120, 'step' => 1,
                            'rules' => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[120]', 'view' => $age,
                            'help' => lang('StaffProfiles.restrictions.age-help')],
                        ['type' => 'html', 'show_in' => ['view'], 'html' => view('staff/restriction_notices', ['notices' => (array) ($data['restriction_notices'] ?? [])])],
                        $hidden,
                    ],
                ],
            ],
            'customScript' => view('scripts/staff_profile', [
                'languagesField' => self::LANGUAGES_FIELD,
                'valuesField'    => self::VALUES_FIELD,
                'languages'      => $languages,
                'attributes'     => $attributes,
                'spoken'         => $this->spokenLanguages($locale, array_column($languages, 'language_code')),
                'proficiencies'  => array_combine(self::PROFICIENCIES, array_map(static fn ($p) => lang('StaffProfiles.proficiency.' . $p), self::PROFICIENCIES)),
                'webLanguages'   => $this->websiteLanguages(),
                'locale'         => $locale,
            ]),
        ]);
    }

    /**
     * Save the languages a person speaks through LINK_INTERNAL_API v1/client/staff/languages/save
     * { business_slug, uid, languages }
     */
    public function saveLanguages(): ResponseInterface
    {
        $post = (array) $this->request->getPost();
        $uid  = trim((string) ($post['uid'] ?? ''));
        $rows = $this->rowsIn((string) ($post[self::LANGUAGES_FIELD] ?? ''));
        if (null === $rows) {
            return $this->saveFailed(422, ['message' => lang('StaffProfiles.invalid-rows')], lang('StaffProfiles.save-failed'));
        }
        $languages = array_map(static fn (array $r): array => [
            'language_code' => (string) ($r['language_code'] ?? ''),
            'proficiency'   => (string) ($r['proficiency'] ?? 'F'),
        ], $rows);
        [$code, $body] = $this->callApi('v1/client/staff/languages/save', ['business_slug' => (string) currentBusinessSlug(), 'uid' => $uid, 'languages' => $languages]);
        return $this->answer($code, $body, $uid, 'StaffProfiles.languages.saved', 'languages', 'StaffProfiles.languages.row-error');
    }

    /**
     * Save a person's values of the business's attributes through LINK_INTERNAL_API v1/client/staff/attributes/save
     * { business_slug, uid, values }. The editor has the values' names in the web pages' languages only; the names of
     * a saved value in other languages are kept, as the API replaces them all.
     */
    public function saveDetails(): ResponseInterface
    {
        $post = (array) $this->request->getPost();
        $uid  = trim((string) ($post['uid'] ?? ''));
        $rows = $this->rowsIn((string) ($post[self::VALUES_FIELD] ?? ''));
        if (null === $rows) {
            return $this->saveFailed(422, ['message' => lang('StaffProfiles.invalid-rows')], lang('StaffProfiles.save-failed'));
        }
        [$code, $body] = $this->callApi('v1/client/staff/profile', ['business_slug' => (string) currentBusinessSlug(), 'uid' => $uid]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, lang('StaffProfiles.save-failed'));
        }
        $storedNames = []; // value public_id => language => text
        foreach ((array) ($body['data']['attributes'] ?? []) as $attribute) {
            foreach ((array) ($attribute['values'] ?? []) as $value) {
                $storedNames[(string) $value['public_id']] = (array) ($value['translations'] ?? []);
            }
        }
        $languages = array_keys($this->websiteLanguages());
        $values    = [];
        foreach ($rows as $row) {
            $id    = (string) ($row['public_id'] ?? '');
            $names = $storedNames[$id] ?? [];
            foreach ($languages as $language) {
                $names[$language] = (string) (((array) ($row['translations'] ?? []))[$language] ?? '');
            }
            $value = [
                'attribute_public_id' => (string) ($row['attribute_public_id'] ?? ''),
                'attribute_value'     => (string) ($row['attribute_value'] ?? ''),
                'sort_order'          => (int) ($row['sort_order'] ?? 0),
                'translations'        => (object) $names,
            ];
            if ('' !== $id) {
                $value['public_id'] = $id;
            }
            $values[] = $value;
        }
        [$code, $body] = $this->callApi('v1/client/staff/attributes/save', ['business_slug' => (string) currentBusinessSlug(), 'uid' => $uid, 'values' => $values]);
        return $this->answer($code, $body, $uid, 'StaffProfiles.details.saved', 'values', 'StaffProfiles.details.row-error');
    }

    /**
     * Save the customers a person does not serve through LINK_INTERNAL_API v1/client/staff/restrictions/save
     * { business_slug, uid, excluded_genders, age_min, age_max }
     */
    public function saveRestrictions(): ResponseInterface
    {
        $post   = (array) $this->request->getPost();
        $uid    = trim((string) ($post['uid'] ?? ''));
        $ageOut = static fn ($v) => '' === trim((string) $v) ? null : trim((string) $v);
        [$code, $body] = $this->callApi('v1/client/staff/restrictions/save', [
            'business_slug'    => (string) currentBusinessSlug(),
            'uid'              => $uid,
            'excluded_genders' => array_values(array_filter(array_map('strval', (array) ($post['excluded_genders'] ?? [])), static fn (string $g) => '' !== $g)),
            'age_min'          => $ageOut($post['age_min'] ?? ''),
            'age_max'          => $ageOut($post['age_max'] ?? ''),
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            // excluded_genders.0 is the form's excluded_genders
            $errors = [];
            foreach ((array) ($body['errors'] ?? []) as $key => $message) {
                $errors[preg_replace('/\..*$/', '', (string) $key)] ??= $message;
            }
            return $this->response->setStatusCode($code === 200 ? 422 : $code)->setJSON([
                'success'   => false,
                'message'   => $body['message'] ?? lang('StaffProfiles.save-failed'),
                'errors'    => $errors ?: null,
                'csrf_hash' => csrf_hash(),
            ]);
        }
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('StaffProfiles.restrictions.saved'),
            'redirect'  => base_url('admin/staff/profile/' . rawurlencode($uid)),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * An editor's rows from its hidden field
     * @return list<array>|null null when it is not a list
     */
    private function rowsIn(string $json): ?array
    {
        $rows = json_decode($json, true);
        return is_array($rows) && array_is_list($rows) ? array_values(array_filter($rows, 'is_array')) : null;
    }

    /**
     * The answer of an editor's save: back to the page on success; on failure, each error of a row
     * (<prefix>.<index>.<field>) told with the row's number, as the editors' rows are not fields of the form
     */
    private function answer(int $code, array $body, string $uid, string $saved, string $prefix, string $rowError): ResponseInterface
    {
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            $errors = [];
            foreach ((array) ($body['errors'] ?? []) as $key => $message) {
                $errors[(string) $key] = preg_match('/^' . preg_quote($prefix, '/') . '\.(\d+)/', (string) $key, $m)
                    ? lang($rowError, [(int) $m[1] + 1, $message]) : $message;
            }
            return $this->response->setStatusCode($code === 200 ? 422 : $code)->setJSON([
                'success'   => false,
                'message'   => $body['message'] ?? lang('StaffProfiles.save-failed'),
                'errors'    => $errors ?: null,
                'csrf_hash' => csrf_hash(),
            ]);
        }
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang($saved),
            'redirect'  => base_url('admin/staff/profile/' . rawurlencode($uid)),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * The languages offered in the languages editor, named in the UI's language, by name
     * @param string       $locale The UI's
     * @param list<string> $saved  The person's language codes, offered even when not in SPOKEN_LANGUAGES
     * @return array<string, string> language_code => name
     */
    private function spokenLanguages(string $locale, array $saved): array
    {
        $out = [];
        foreach (array_unique([...self::SPOKEN_LANGUAGES, ...$saved]) as $code) {
            $name       = class_exists(Locale::class) ? Locale::getDisplayName($code, $locale) : '';
            $out[$code] = '' === $name || $name === $code ? $code : $name;
        }
        asort($out, SORT_NATURAL | SORT_FLAG_CASE);
        return $out;
    }
}
