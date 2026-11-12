<?php

namespace App\Controllers;

use App\Models\AuthMasterModel;
use App\Models\BusinessClientLinkModel;
use App\Models\BusinessMasterModel;
use App\Models\BusinessStoreModel;
use App\Models\BusinessTypeModel;
use App\Models\ClientMasterModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use CURLFile;
use RuntimeException;

class Admin extends BaseController
{
    /**
     * The profile form, section => fields. Columns not listed here (ids, uid, avatar_path, account type/status,
     * admin and internal notes, ID documents, timestamps) are never shown to the client.
     */
    private const PROFILE_SECTIONS = [
        'account'        => ['email_address', 'telephone_number', 'password_expiry', 'full_name', 'otp_mode'],
        'personal'       => ['legal_first_name', 'legal_middle_name', 'legal_last_name', 'gender', 'date_of_birth', 'nationality_code'],
        'public-profile' => ['display_name', 'status_emoji', 'status_message', 'bio'],
        'preferences'    => ['preferred_language', 'marketing_opt_in'],
    ];

    /**
     * The fields the client may change, as v1/client/account/update takes them (auth_master / client_master);
     * every other field of the form is shown disabled and never forwarded
     */
    private const PROFILE_ACCOUNT_EDITABLE = ['telephone_number', 'full_name', 'otp_mode'];
    private const PROFILE_CLIENT_EDITABLE  = ['legal_first_name', 'legal_middle_name', 'legal_last_name', 'display_name',
        'status_message', 'status_emoji', 'gender', 'date_of_birth', 'nationality_code', 'preferred_language', 'bio',
        'marketing_opt_in'];

    /** The create-business form, section => fields; all of them go to v1/client/business/create as "business" */
    private const BUSINESS_SECTIONS = [
        // the country and currency come first: they are fixed once created, and the rest depends on the country
        'business' => ['business_type_public_id', 'business_name', 'business_slug', 'country_code', 'currency_code',
            'business_description', 'business_mood_and_tone'],
        'audience' => ['lgbt_audience', 'naturist_audience', 'age_restricted'],
        'charges'  => ['tip_policy', 'tip_value', 'tip_unit', 'tip_value_kind', 'service_charge_value', 'service_charge_unit'],
        'booking'  => ['booking_horizon_days', 'booking_notice_unit', 'booking_notice_value', 'booking_notice_time'],
        'cancellation' => ['cancel_notice_unit', 'cancel_notice_value', 'cancel_notice_time', 'late_cancel_allowed',
            'late_cancel_fee_value', 'late_cancel_fee_unit', 'no_show_fee_value', 'no_show_fee_unit'],
        'contact'  => ['contact_person_name', 'contact_email_address', 'contact_phone_number', 'contact_website'],
        'address'  => ['contact_address_line_1', 'contact_address_line_2', 'contact_city', 'contact_subdivision_code',
            'contact_postal_code'],
        'billing'  => ['tax_id'],
    ];
    private const BUSINESS_FIELDS = ['business_type_public_id', 'business_name', 'business_slug', 'business_description',
        'business_mood_and_tone', 'lgbt_audience', 'naturist_audience', 'age_restricted', 'tip_policy', 'tip_value', 'tip_unit',
        'tip_value_kind', 'service_charge_value', 'service_charge_unit', 'booking_horizon_days', 'booking_notice_unit',
        'booking_notice_value', 'booking_notice_time', 'cancel_notice_unit', 'cancel_notice_value', 'cancel_notice_time',
        'late_cancel_allowed', 'late_cancel_fee_value', 'late_cancel_fee_unit', 'no_show_fee_value', 'no_show_fee_unit', 'contact_person_name', 'contact_email_address', 'contact_phone_number', 'contact_website',
        'contact_address_line_1', 'contact_address_line_2', 'contact_city', 'contact_subdivision_code', 'contact_postal_code',
        'country_code', 'currency_code', 'tax_id'];

    /**
     * The edit-business form, section => fields. country_code and currency_code are shown but never sent: they are
     * fixed once the business is created. tax_id (under the postal code) and the names in other languages are not
     * kept in the session business, but come beside it from v1/client/business/switch; when they cannot be fetched,
     * their fields are left out, and so the API keeps them.
     */
    private const BUSINESS_EDIT_SECTIONS = [
        'business' => ['business_type_public_id', 'business_name', 'business_slug', 'country_code', 'currency_code',
            'business_description', 'business_mood_and_tone'],
        'audience' => ['lgbt_audience', 'naturist_audience', 'age_restricted'],
        'charges'  => ['tip_policy', 'tip_value', 'tip_unit', 'tip_value_kind', 'service_charge_value', 'service_charge_unit'],
        'booking'  => ['booking_horizon_days', 'booking_notice_unit', 'booking_notice_value', 'booking_notice_time'],
        'cancellation' => ['cancel_notice_unit', 'cancel_notice_value', 'cancel_notice_time', 'late_cancel_allowed',
            'late_cancel_fee_value', 'late_cancel_fee_unit', 'no_show_fee_value', 'no_show_fee_unit'],
        'contact'  => ['contact_person_name', 'contact_email_address', 'contact_phone_number', 'contact_website'],
        'address'  => ['contact_address_line_1', 'contact_address_line_2', 'contact_city', 'contact_subdivision_code',
            'contact_postal_code', 'tax_id'],
    ];
    private const BUSINESS_EDIT_FIELDS = ['business_type_public_id', 'business_name', 'business_slug', 'business_description',
        'business_mood_and_tone', 'lgbt_audience', 'naturist_audience', 'age_restricted', 'tip_policy', 'tip_value', 'tip_unit',
        'tip_value_kind', 'service_charge_value', 'service_charge_unit', 'booking_horizon_days', 'booking_notice_unit',
        'booking_notice_value', 'booking_notice_time', 'cancel_notice_unit', 'cancel_notice_value', 'cancel_notice_time',
        'late_cancel_allowed', 'late_cancel_fee_value', 'late_cancel_fee_unit', 'no_show_fee_value', 'no_show_fee_unit', 'contact_person_name', 'contact_email_address', 'contact_phone_number', 'contact_website',
        'contact_address_line_1', 'contact_address_line_2', 'contact_city', 'contact_subdivision_code', 'contact_postal_code',
        'tax_id'];

    /** The business form's notices with a time of day, shown for a notice in days only (scripts/notice_time) */
    private const NOTICE_TIMES = [
        ['unit' => 'booking_notice_unit', 'time' => 'booking_notice_time'],
        ['unit' => 'cancel_notice_unit', 'time' => 'cancel_notice_time'],
    ];

    /** Hidden field of the edit-business form: the slug the form was opened with, so a session switched meanwhile is seen */
    private const BUSINESS_CURRENT_SLUG = 'current_business_slug';

    /** The store form, section => fields; all of them go to v1/client/store/create and store/edit as "store" */
    private const STORE_SECTIONS = [
        'store'   => ['store_name', 'store_type', 'store_timezone', 'store_phone_number'],
        'address' => BusinessStoreModel::ADDRESS_FIELDS,
    ];
    private const STORE_FIELDS = ['store_name', 'store_type', 'store_timezone', 'store_phone_number', ...BusinessStoreModel::ADDRESS_FIELDS];

    /** Form field name of the business name in a language: translation_<language_code> */
    private const TRANSLATION_PREFIX = 'translation_';

    /**
     * The store's texts in other languages (business_store_translation), column => [form field prefix, longest
     * length]: the form field is the prefix followed by the language_code. The name keeps TRANSLATION_PREFIX.
     */
    private const STORE_TRANSLATION_FIELDS = [
        'store_name'           => [self::TRANSLATION_PREFIX, 255],
        'store_address_line_1' => ['translation_address_line_1_', 255],
        'store_address_line_2' => ['translation_address_line_2_', 255],
        'store_city'           => ['translation_city_', 128],
    ];

    /** otp_mode values only the system sets (telephone OTP is not available yet) */
    private const OTP_MODE_DISABLED = [AuthMasterModel::OTP_TELEPHONE];

    /**
     * Landing page after sign-in (placeholder until the client dashboard is built).
     */
    public function dashboard(): string
    {
        $locale = $this->request->getLocale();
        $data   = [
            'locale' => $locale,
            'slug'   => 'dashboard',
            'user'   => session()->get('user') ?? [],
        ];
        return view('admin_dashboard', $data);
    }

    /**
     * The signed-in client's own profile (v1/client/account), opened in view mode; the Edit button switches the
     * same page to edit mode. Below it: the profile picture upload and the password change.
     */
    public function profile(): string
    {
        [$code, $body] = $this->callApi('v1/client/account', []);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException($body['message'] ?? lang('Profile.form.load-failed'));
        }
        // keyed by field name, as the form looks values up by the field's 'name'
        $values = (array) ($body['data']['account'] ?? []) + (array) ($body['data']['profile'] ?? []);
        $user   = (array) session('user');
        $uid    = (string) ($user['uid'] ?? '');
        // the session copy carries a version after an upload (see imageUpload()), so the new picture is not a cached one
        $avatar  = (string) ($values['avatar_path'] ?? '');
        $session = (string) ($user['avatar_path'] ?? '');
        if ('' !== $avatar && strtok($session, '?') === $avatar) {
            $avatar = $session;
        }
        // with no picture, the session holds the initials avatar (from sign-in or imageDelete()), shown in its place
        $fallback = '' === $avatar ? avatarUrl($session) : avatarUrl(null);
        $locale   = $this->request->getLocale();
        $data     = [
            'locale'     => $locale,
            'slug'       => 'profile',
            'user'       => $user,
            'formConfig' => [
                'form_id'  => 'profile-form',
                'title'    => lang('Profile.form.title'),
                'subtitle' => lang('Profile.form.subtitle'),
                'mode'     => 'view',
                'id_field' => 'uid',
                'id'       => $uid,
                'method'   => 'POST',
                'locale'   => $locale,
                'urls'     => [
                    'submit' => base_url('admin/profile/save'),
                    'edit'   => base_url('admin/profile'),
                ],
                'messages' => ['saved' => lang('Profile.form.saved'), 'saveFailed' => lang('Profile.form.failed')],
                'values'   => $values,
                'fields'   => $this->profileFields(),
            ],
            // a stand-alone upload form: the API saves the picture to avatar_path of auth_master row 'id', in a file
            // named after the account's public_id (pictures uploaded before are named after the uid until replaced)
            'extraForms' => [[
                'form_id'   => 'profile-picture-form',
                'title'     => lang('Profile.picture.title'),
                'subtitle'  => lang('Profile.picture.subtitle'),
                'id_prefix' => 'picture-',
                'id_field'  => 'id',
                'id'        => (int) ($user['id'] ?? 0),
                'urls'      => ['submit' => base_url('admin/image-upload'), 'delete' => base_url('admin/image-delete')],
                'values'    => ['used-for' => 'profile-picture', 'image-name' => (string) ($values['public_id'] ?? '')],
                'fields'    => [
                    ['name' => 'used-for', 'type' => 'hidden'],
                    ['name' => 'image-name', 'type' => 'hidden'],
                    ['name' => 'image', 'type' => 'file', 'label' => lang('Profile.picture.label'), 'rules' => 'required',
                     'preview' => '' === $avatar ? $fallback : avatarUrl($avatar), 'stored' => '' !== $avatar, 'default_preview' => $fallback],
                ],
            ], [
                // the API checks the current password and the new one's rules (8+ characters, a letter and a number)
                'form_id'   => 'change-password-form',
                'title'     => lang('Profile.password.title'),
                'subtitle'  => lang('Profile.password.subtitle'),
                'mode'      => 'edit',
                'id_prefix' => 'password-',
                'id_field'  => 'uid',
                'id'        => $uid,
                'urls'      => ['submit' => base_url('admin/profile/password')],
                'labels'    => ['save' => lang('Profile.password.submit')],
                'messages'  => ['saved' => lang('Profile.password.changed'), 'saveFailed' => lang('Profile.password.failed')],
                'fields'    => [
                    ['name' => 'current_password', 'type' => 'password', 'label' => lang('Profile.password.current'), 'rules' => 'required', 'autocomplete' => 'current-password'],
                    ['name' => 'new_password', 'type' => 'password', 'label' => lang('Profile.password.new'), 'rules' => 'required|min_length[8]', 'autocomplete' => 'new-password',
                     'help' => lang('Profile.password.new-help')],
                    ['name' => 'confirm_password', 'type' => 'password', 'label' => lang('Profile.password.confirm'), 'rules' => 'required|matches[new_password]', 'autocomplete' => 'new-password',
                     'messages' => ['matches' => lang('Profile.password.mismatch')]],
                ],
            ]],
        ];
        return view('generic_form', $data);
    }

    /**
     * Save the signed-in client's own profile through LINK_INTERNAL_API v1/client/account/update.
     * The API takes the uid from the token, and only the editable fields are forwarded.
     */
    public function profileEditPost(): ResponseInterface
    {
        $post    = (array) $this->request->getPost();
        $account = array_intersect_key($post, array_flip(self::PROFILE_ACCOUNT_EDITABLE));
        $profile = array_intersect_key($post, array_flip(self::PROFILE_CLIENT_EDITABLE));
        if (in_array((string) ($account['otp_mode'] ?? ''), self::OTP_MODE_DISABLED, true)) {
            unset($account['otp_mode']);
        }

        [$code, $body] = $this->callApi('v1/client/account/update', ['account' => $account, 'profile' => $profile]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, lang('Profile.form.failed'));
        }

        // the layout reads the names from the session user, and BaseController the UI language (preferred_language)
        $user  = (array) session('user');
        $saved = array_map(static fn ($value) => '' === trim((string) $value) ? null : trim((string) $value), $account + $profile);
        session()->set('user', array_merge($user, array_intersect_key($saved, $user + ['preferred_language' => null])));
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('Profile.form.saved'),
            'redirect'  => base_url('admin/profile'), // reloaded, so a new preferred_language shows at once
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Change the signed-in client's own password through LINK_INTERNAL_API v1/client/change-password,
     * which checks the current password and the new one's rules
     */
    public function profilePasswordPost(): ResponseInterface
    {
        $current = (string) $this->request->getPost('current_password');
        $new     = (string) $this->request->getPost('new_password');
        if ($new !== (string) $this->request->getPost('confirm_password')) {
            return $this->response->setStatusCode(422)->setJSON([
                'success'   => false,
                'message'   => lang('Profile.password.mismatch'),
                'errors'    => ['confirm_password' => lang('Profile.password.mismatch')],
                'csrf_hash' => csrf_hash(),
            ]);
        }

        [$code, $body] = $this->callApi('v1/client/change-password', ['current_password' => $current, 'new_password' => $new]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, lang('Profile.password.failed'));
        }

        // the session user keeps the new expiry, as the sign-in response would
        $user = (array) session('user');
        session()->set('user', ['password_expiry' => $body['data']['password_expiry'] ?? null] + $user);
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('Profile.password.changed'),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Upload an image through LINK_INTERNAL_API v1/client/image-upload, for the stand-alone upload forms:
     * multipart { used-for, image-name, id, image }, forwarded as sent (the API only lets a client change their own row).
     * A new profile picture also goes into the session for the layout.
     */
    public function imageUpload(): ResponseInterface
    {
        $file = $this->request->getFile('image');
        if (null === $file || ! $file->isValid()) {
            $tooLarge = null !== $file && in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            $message  = lang($tooLarge ? 'Profile.picture.too-large' : 'Profile.picture.choose');
            return $this->response->setStatusCode($tooLarge ? 413 : 422)->setJSON([
                'success'   => false,
                'message'   => $message,
                'errors'    => ['image' => $message],
                'csrf_hash' => csrf_hash(),
            ]);
        }

        $usedFor = (string) $this->request->getPost('used-for');
        $id      = (string) $this->request->getPost('id');
        [$code, $body] = $this->callApi('v1/client/image-upload', [
            'used-for'   => $usedFor,
            'image-name' => (string) $this->request->getPost('image-name'),
            'id'         => $id,
            'image'      => new CURLFile($file->getTempName(), $file->getMimeType(), $file->getClientName()),
        ], true);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, lang('Profile.picture.upload-failed'));
        }

        // the file name stays the same on a re-upload, so the version makes browsers fetch the new image
        $url  = (string) ($body['data']['url'] ?? '');
        $url .= ('' === $url ? '' : '?v=' . time());
        $user = (array) session('user');
        if ('profile-picture' === $usedFor && '' !== $url && $id === (string) ($user['id'] ?? '')) {
            session()->set('user', ['avatar_path' => $url] + $user);
        }
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('Profile.picture.uploaded'),
            'url'       => $url,
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Delete an image through LINK_INTERNAL_API v1/client/image-delete, for the stand-alone upload forms:
     * { used-for, id }, forwarded as sent. For a profile picture the API answers with 'url', the initials avatar
     * (null when the name gives no initials), which takes the picture's place in the session and the form's thumbnail.
     */
    public function imageDelete(): ResponseInterface
    {
        $usedFor = (string) $this->request->getPost('used-for');
        $id      = (string) $this->request->getPost('id');
        [$code, $body] = $this->callApi('v1/client/image-delete', ['used-for' => $usedFor, 'id' => $id]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, lang('Profile.picture.delete-failed'));
        }

        $url  = (string) ($body['data']['url'] ?? '');
        $user = (array) session('user');
        if ('profile-picture' === $usedFor && $id === (string) ($user['id'] ?? '')) {
            session()->set('user', ['avatar_path' => '' === $url ? null : $url] + $user);
        }
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('Profile.picture.deleted'),
            'url'       => '' === $url ? '' : avatarUrl($url),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * The form to create a business, where a client active in no business lands after sign-in (see AuthFilter).
     * The type options come from LINK_INTERNAL_API v1/client/business-types (active types only).
     */
    public function createBusiness(): string
    {
        $locale = $this->request->getLocale();
        $types  = $this->businessTypeOptions($locale);
        $user   = (array) session('user');
        $data = [
            'locale'     => $locale,
            'slug'       => 'create-business',
            'user'       => $user,
            'formConfig' => [
                'form_id'  => 'create-business-form',
                'title'    => lang('CreateBusiness.form.title'),
                'subtitle' => lang('CreateBusiness.form.subtitle'),
                'mode'     => 'create',
                'locale'   => $locale,
                'urls'     => ['submit' => base_url('admin/create-business')],
                'labels'   => ['save' => lang('CreateBusiness.form.submit')],
                'messages' => ['saved' => lang('Business.created'), 'saveFailed' => lang('Business.saveFailed')],
                'fields'   => $this->businessFields($types, $user),
            ],
            // fills the currency and subdivision dropdowns for the country picked; shows the time of day for a notice in days
            'customScript' => view('scripts/create_business', [
                'formId' => 'create-business-form',
                'urls'   => ['currencies' => base_url('admin/api/currencies'), 'subdivisions' => base_url('admin/api/subdivisions'),
                             'slug' => base_url('admin/api/generate-slug')],
                'select' => lang('Form.select'),
                'failed' => lang('CreateBusiness.form.locale-failed'),
            ]) . view('scripts/notice_time', ['formId' => 'create-business-form', 'days' => BusinessMasterModel::NOTICE_DAYS, 'pairs' => self::NOTICE_TIMES]),
        ];
        return view('generic_form', $data);
    }

    /**
     * Create a business through LINK_INTERNAL_API v1/client/business/create, with the signed-in client as its owner:
     * { business: {...}, translations: {language_code: business_name} }. A client who had no business yet gets
     * this one as their session business, as sign-in would have given it.
     */
    public function createBusinessPost(): ResponseInterface
    {
        $post     = (array) $this->request->getPost();
        $business = array_intersect_key($post, array_flip(self::BUSINESS_FIELDS));
        // a new business has no web pages, so no languages to name it in yet
        $names    = [];

        [$code, $body] = $this->callApi('v1/client/business/create', ['business' => $business, 'translations' => (object) $names]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $this->translationErrors($body), lang('Business.saveFailed'));
        }

        if (needsBusiness()) {
            $saved = array_map(static fn ($value) => '' === trim((string) $value) ? null : trim((string) $value), $business);
            unset($saved['tax_id']); // sign-in does not send it either
            session()->set('business', [
                'client_role'         => BusinessClientLinkModel::ROLE_OWNER,
                'client_status'       => BusinessClientLinkModel::STATUS_ACTIVE,
                'is_default_business' => BusinessClientLinkModel::DEFAULT_YES,
                'start_date'          => date('Y-m-d'),
                'business'            => ['business_slug' => $body['data']['business_slug'] ?? $saved['business_slug'] ?? null] + $saved,
            ]);
        }
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('Business.created'),
            'redirect'  => base_url('admin/dashboard'),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * The businesses the signed-in client works in (generic_list), with buttons to make one their default (the
     * one sign-in opens) and to switch the session to another. No edit link; the Add button creates a business.
     */
    public function myBusinesses(): string
    {
        $locale = $this->request->getLocale();
        $config = $this->myBusinessesListConfig();
        $data   = [
            'locale'       => $locale,
            'slug'         => 'my-businesses',
            'user'         => session()->get('user') ?? [],
            'listConfig'   => $config,
            'customScript' => view('scripts/my_businesses', [
                'tableId' => $config['table_id'],
                'urls'    => [
                    'make-default' => base_url('admin/my-businesses/make-default'),
                    'switch'       => base_url('admin/my-businesses/switch'),
                ],
                'failed'  => lang('MyBusinesses.action-failed'),
            ]),
        ];
        return view('generic_list', $data);
    }

    /**
     * DataTables data of myBusinesses(), from LINK_INTERNAL_API v1/client/businesses. A client has a handful of
     * businesses, so the API sends them all and the search, order and paging are done here.
     */
    public function myBusinessesData(): ResponseInterface
    {
        $config = $this->myBusinessesListConfig();
        $params = lb_request($this->request, $config);
        [$code, $body] = $this->callApi('v1/client/businesses', []);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, []) + [
                'error'     => $body['message'] ?? lang('MyBusinesses.load-failed'),
                'csrf_hash' => csrf_hash(),
            ]);
        }

        $current   = (string) (session('business')['business']['business_slug'] ?? '');
        $countries = listCountries();
        $rows      = [];
        foreach ((array) ($body['data']['businesses'] ?? []) as $business) {
            $slug    = (string) ($business['business_slug'] ?? '');
            $country = (string) ($business['country_code'] ?? '');
            $rows[]  = [
                'business_slug'       => $slug,
                'business_name'       => (string) ($business['business_name'] ?? ''),
                'country'             => $countries[$country] ?? $country,
                'client_role'         => (string) ($business['client_role'] ?? ''),
                'is_default_business' => (string) ($business['is_default_business'] ?? ''),
                'actions'             => $this->myBusinessActions($slug, (string) ($business['business_name'] ?? ''),
                    $slug === $current, ($business['is_default_business'] ?? '') === BusinessClientLinkModel::DEFAULT_YES),
            ];
        }
        $total = count($rows);

        // search the text the client sees, so a role or country is found by its label
        $roles = lang('BusinessClientLink.enum.client_role');
        if ('' !== $params['search']) {
            $needle = mb_strtolower($params['search']);
            $rows   = array_values(array_filter($rows, static fn (array $row) => str_contains(
                mb_strtolower($row['business_name'] . ' ' . $row['country'] . ' ' . ($roles[$row['client_role']] ?? $row['client_role'])),
                $needle
            )));
        }
        $filtered = count($rows);
        foreach (array_reverse($params['order']) as [$column, $direction]) {
            // stable, so the earlier order columns win
            usort($rows, static function (array $a, array $b) use ($column, $direction, $roles) {
                $left  = 'client_role' === $column ? ($roles[$a[$column]] ?? $a[$column]) : $a[$column];
                $right = 'client_role' === $column ? ($roles[$b[$column]] ?? $b[$column]) : $b[$column];
                $cmp   = strnatcasecmp((string) $left, (string) $right);
                return 'DESC' === $direction ? -$cmp : $cmp;
            });
        }
        $rows = array_slice($rows, $params['start'], $params['length']);

        return $this->response->setJSON(lb_response($params['draw'], $total, $filtered, $rows) + ['csrf_hash' => csrf_hash()]);
    }

    /**
     * Make a business the signed-in client's default one through LINK_INTERNAL_API v1/client/business/make-default
     * { business_slug }; the session business follows, so it stays as the API has it.
     */
    public function myBusinessesMakeDefault(): ResponseInterface
    {
        $slug = trim((string) ($this->request->getJsonVar('business_slug') ?? $this->request->getPost('business_slug')));
        [$code, $body] = $this->callApi('v1/client/business/make-default', ['business_slug' => $slug]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->actionFailed($code, $body);
        }

        $business = session('business');
        if (is_array($business)) {
            $isCurrent = ($business['business']['business_slug'] ?? null) === $slug;
            $business['is_default_business'] = $isCurrent ? BusinessClientLinkModel::DEFAULT_YES : BusinessClientLinkModel::DEFAULT_NO;
            session()->set('business', $business);
        }
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('MyBusinesses.default-saved'),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Switch the session to another business of the signed-in client: LINK_INTERNAL_API v1/client/business/switch
     * { business_slug } gives it as sign-in does, and it replaces the session business.
     */
    public function myBusinessesSwitch(): ResponseInterface
    {
        $slug = trim((string) ($this->request->getJsonVar('business_slug') ?? $this->request->getPost('business_slug')));
        [$code, $body] = $this->callApi('v1/client/business/switch', ['business_slug' => $slug]);
        $business = $body['data']['business'] ?? null;
        if ($code !== 200 || ($body['status'] ?? '') !== 'success' || ! is_array($business)) {
            return $this->actionFailed($code, $body);
        }

        session()->set('business', $business);
        return $this->response->setJSON([
            'success'   => true,
            'message'   => lang('MyBusinesses.switched', [(string) ($business['business']['business_name'] ?? '')]),
            'redirect'  => base_url('admin/dashboard'),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * The session business (the one the client is working in), opened in view mode; the Edit button switches the
     * same page to edit mode. Its owners and managers only (see BusinessManagerFilter). The values are fetched
     * afresh through v1/client/business/switch, so a change made elsewhere shows. Country and currency are shown
     * but cannot be changed; changing the business slug asks first, as it moves the store's address at once.
     */
    public function business(): string
    {
        $locale   = $this->request->getLocale();
        $business = $this->refreshSessionBusiness(null, $names, $taxId) ?? (array) session('business');
        // a session business kept from before the API had tips and service charges: what the API stored for it then
        $values   = (array) ($business['business'] ?? []) + BusinessMasterModel::CHARGE_DEFAULTS + BusinessMasterModel::BOOKING_DEFAULTS
            + BusinessMasterModel::CANCEL_DEFAULTS;
        foreach (['booking_notice_time', 'cancel_notice_time'] as $name) {
            $values[$name] = null === $values[$name] ? null : substr((string) $values[$name], 0, 5);
        }
        $slug     = (string) ($values['business_slug'] ?? '');
        $values[self::BUSINESS_CURRENT_SLUG] = $slug;
        foreach ($names ?? [] as $language => $name) {
            $values[self::TRANSLATION_PREFIX . $language] = $name;
        }
        if (null !== $taxId) {
            $values['tax_id'] = $taxId['tax_id'];
        }
        $formId = 'edit-business-form';
        $data   = [
            'locale'       => $locale,
            'slug'         => 'business',
            'user'         => (array) session('user'),
            'formConfig'   => [
                'form_id'  => $formId,
                'title'    => lang('EditBusiness.form.title'),
                'subtitle' => lang('EditBusiness.form.subtitle', [(string) ($values['business_name'] ?? '')]),
                'mode'     => 'view',
                'id_field' => self::BUSINESS_CURRENT_SLUG,
                'id'       => $slug,
                'locale'   => $locale,
                'urls'     => [
                    'submit' => base_url('admin/business/save'),
                    'edit'   => base_url('admin/business'),
                ],
                'messages' => ['saved' => lang('EditBusiness.form.saved'), 'saveFailed' => lang('EditBusiness.form.failed')],
                'values'   => $values,
                'fields'   => $this->businessEditFields($this->businessTypeOptions($locale), (string) ($values['country_code'] ?? ''), $slug, null !== $names, null !== $taxId),
            ],
            // asks before a changed business slug is saved; shows the time of day for a notice in days
            'customScript' => view('scripts/edit_business', [
                'formId'  => $formId,
                'slug'    => $slug,
                'baseUrl' => $this->storeBaseUrl(),
                'confirm' => [
                    'title'   => lang('EditBusiness.slug-confirm.title'),
                    'text'    => lang('EditBusiness.slug-confirm.text'),
                    'confirm' => lang('EditBusiness.slug-confirm.confirm'),
                    'cancel'  => lang('EditBusiness.slug-confirm.cancel'),
                ],
            ]) . view('scripts/notice_time', ['formId' => $formId, 'days' => BusinessMasterModel::NOTICE_DAYS, 'pairs' => self::NOTICE_TIMES]),
        ];
        return view('generic_form', $data);
    }

    /**
     * Save the session business through LINK_INTERNAL_API v1/client/business/edit { business_slug, business,
     * translations }. country_code and currency_code are never forwarded, so the API keeps them; tax_id only when the
     * form has it (see business()). The names
     * in the form's languages replace the stored ones, and those in other languages are kept (fetched afresh, as the
     * API replaces them all); a form without them sends no translations. A renamed business slug becomes the
     * session business's key at once.
     */
    public function businessEditPost(): ResponseInterface
    {
        $current = (string) currentBusinessSlug();
        // the session switched to another business (e.g. in another tab) since the form was opened
        if ($current !== (string) $this->request->getPost(self::BUSINESS_CURRENT_SLUG)) {
            return $this->response->setStatusCode(409)->setJSON([
                'success'   => false,
                'message'   => lang('Business.notFound'),
                'redirect'  => base_url('admin/business'),
                'csrf_hash' => csrf_hash(),
            ]);
        }
        $post     = (array) $this->request->getPost();
        $business = array_intersect_key($post, array_flip(self::BUSINESS_EDIT_FIELDS));
        $payload  = ['business_slug' => $current, 'business' => $business];
        $posted   = array_filter(array_keys($this->websiteLanguages()), static fn (string $language) => array_key_exists(self::TRANSLATION_PREFIX . $language, $post));
        if ([] !== $posted) {
            // the API replaces all the names, so the names in other languages than the form's are sent as they are
            if (null === $this->refreshSessionBusiness($current, $names)) {
                return $this->saveFailed(500, [], lang('EditBusiness.form.failed'));
            }
            foreach ($posted as $language) {
                $names[$language] = (string) $post[self::TRANSLATION_PREFIX . $language];
            }
            $payload['translations'] = (object) $names;
        }

        [$code, $body] = $this->callApi('v1/client/business/edit', $payload);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $this->translationErrors($body), lang('EditBusiness.form.failed'));
        }

        // the session business follows: fetched afresh, or else patched with what was saved
        $slug = (string) ($body['data']['business_slug'] ?? $business['business_slug'] ?? $current);
        if (null === $this->refreshSessionBusiness($slug)) {
            $saved   = array_map(static fn ($value) => '' === trim((string) $value) ? null : trim((string) $value), $business);
            unset($saved['tax_id']); // never kept in the session
            $session = (array) session('business');
            $session['business'] = ['business_slug' => $slug] + $saved + (array) ($session['business'] ?? []);
            session()->set('business', $session);
        }
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('EditBusiness.form.saved'),
            'redirect'  => base_url('admin/business'),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * The stores of the session business (generic_list); its owners and managers only. Each row opens the store.
     */
    public function stores(): string
    {
        $data = [
            'locale'     => $this->request->getLocale(),
            'slug'       => 'stores',
            'user'       => (array) session('user'),
            'listConfig' => $this->storesListConfig() + ['add_disabled' => $this->storeCapReached()],
        ];
        return view('generic_list', $data);
    }

    /**
     * DataTables data of stores(), from LINK_INTERNAL_API v1/client/stores { business_slug }. A business has a
     * handful of stores, so the API sends them all and the search, order and paging are done here.
     */
    public function storesData(): ResponseInterface
    {
        $config = $this->storesListConfig();
        $params = lb_request($this->request, $config);
        [$stores, $error] = $this->loadStores();
        if (null !== $error) {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, []) + ['error' => $error, 'csrf_hash' => csrf_hash()]);
        }

        $locale       = $this->request->getLocale();
        $types        = lang('BusinessStore.enum.store_type');
        $subdivisions = listSupportedSubdivisions((string) (session('business')['business']['country_code'] ?? ''));
        $rows         = [];
        foreach ($stores as $store) {
            $phone       = (string) ($store['store_phone_number'] ?? '');
            $subdivision = (string) ($store['store_subdivision_code'] ?? '');
            $location    = array_filter([(string) ($store['store_city'] ?? ''), $subdivisions[$subdivision] ?? $subdivision]);
            $rows[]      = [
                'public_id'          => (string) ($store['public_id'] ?? ''),
                // the name in the UI language, when the store has one
                'store_name'         => self::storeName($store, $locale),
                'store_type'         => (string) ($store['store_type'] ?? ''),
                'store_phone_number' => '' === $phone ? '' : formatPhoneNumber($phone),
                'location'           => implode(', ', $location),
            ];
        }
        $total = count($rows);
        $rows  = $this->searchAndOrder($rows, $params, static fn (array $row, string $column) => 'store_type' === $column
            ? (string) ($types[$row[$column]] ?? $row[$column]) : (string) $row[$column]);
        $filtered = count($rows);
        $rows     = lb_rows(array_slice($rows, $params['start'], $params['length']), $config);

        return $this->response->setJSON(lb_response($params['draw'], $total, $filtered, $rows) + ['csrf_hash' => csrf_hash()]);
    }

    /**
     * The form to add a store to the session business, or, with $publicId, one of its stores opened in view mode
     * (the Edit button switches the page to edit mode). Address fields hide for an online store.
     * @param string|null $publicId business_store.public_id
     */
    public function storeForm(?string $publicId = null): string
    {
        $values = [];
        if (null !== $publicId) {
            $store = $this->findStore($publicId);
            if (null === $store) {
                throw PageNotFoundException::forPageNotFound(lang('Stores.form.not-found'));
            }
            $values = $store;
            foreach ((array) ($store['translations'] ?? []) as $language => $texts) {
                foreach (self::STORE_TRANSLATION_FIELDS as $column => [$prefix]) {
                    $values[$prefix . $language] = (string) (((array) $texts)[$column] ?? '');
                }
            }
            unset($values['translations']);
        }
        $locale  = $this->request->getLocale();
        $country = (string) (session('business')['business']['country_code'] ?? '');
        $formId  = 'store-form';
        $data    = [
            'locale'       => $locale,
            'slug'         => 'stores',
            'page_title'   => $values['store_name'] ?? lang('System.page.store-create'),
            'breadcrumbs'  => [
                base_url('admin/business/stores') => lang('System.page.stores'),
            ],
            'user'         => (array) session('user'),
            'formConfig'   => [
                'form_id'  => $formId,
                'title'    => lang(null === $publicId ? 'Stores.form.create-title' : 'Stores.form.edit-title'),
                'subtitle' => lang('Stores.form.subtitle', [(string) currentBusinessName()]),
                'mode'     => null === $publicId ? 'create' : 'view',
                'id_field' => 'public_id',
                'id'       => (string) $publicId,
                'locale'   => $locale,
                'urls'     => [
                    'submit' => base_url('admin/business/stores/save'),
                    'edit'   => null === $publicId ? '' : base_url('admin/business/stores/edit/' . rawurlencode($publicId)),
                    'list'   => base_url('admin/business/stores'),
                ],
                'messages' => ['saved' => lang(null === $publicId ? 'Stores.form.created' : 'Stores.form.saved'), 'saveFailed' => lang('Stores.form.failed')],
                'values'   => $values,
                'fields'   => $this->storeFields($country),
            ],
            'customScript' => view('scripts/store_form', [
                'formId'  => $formId,
                'online'  => BusinessStoreModel::TYPE_ONLINE,
                'address' => [...BusinessStoreModel::ADDRESS_FIELDS, ...$this->storeAddressTranslationNames()],
            ]),
            // a saved store's opening hours are a page of their own
            'extraHtml'    => null === $publicId ? '' : view('stores/hours_link', ['url' => base_url('admin/business/stores/hours/' . rawurlencode($publicId))]),
        ];
        return view('generic_form', $data);
    }

    /**
     * Create or save a store of the session business through LINK_INTERNAL_API: v1/client/store/create
     * { business_slug, store, translations }, or, with public_id, v1/client/store/edit { public_id, store, translations }.
     * translations is language_code => {store_name, store_address_line_1, store_address_line_2, store_city}. The form
     * has them in the web pages' languages only; on an edit, the other languages' are kept, as the API replaces them all.
     */
    public function storeSavePost(): ResponseInterface
    {
        $post     = (array) $this->request->getPost();
        $publicId = trim((string) ($post['public_id'] ?? ''));
        $store    = array_intersect_key($post, array_flip(self::STORE_FIELDS));
        $names    = [];
        if ('' !== $publicId) {
            $stored = $this->findStore($publicId);
            if (null === $stored) {
                return $this->saveFailed(404, ['message' => lang('Stores.form.not-found')], lang('Stores.form.failed'));
            }
            $names = (array) ($stored['translations'] ?? []);
        }
        foreach (array_keys($this->websiteLanguages()) as $language) {
            $names[$language] = [];
            foreach (self::STORE_TRANSLATION_FIELDS as $column => [$prefix]) {
                // an online store's address fields are disabled, so not posted; the API clears them either way
                $names[$language][$column] = (string) ($post[$prefix . $language] ?? '');
            }
        }

        [$code, $body] = '' === $publicId
            ? $this->callApi('v1/client/store/create', ['business_slug' => (string) currentBusinessSlug(), 'store' => $store, 'translations' => (object) $names])
            : $this->callApi('v1/client/store/edit', ['public_id' => $publicId, 'store' => $store, 'translations' => (object) $names]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->storeFailed($code, $body);
        }
        $savedId = (string) ($body['data']['public_id'] ?? $publicId);
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('' === $publicId ? 'Stores.form.created' : 'Stores.form.saved'),
            'redirect'  => '' === $savedId ? base_url('admin/business/stores') : base_url('admin/business/stores/edit/' . rawurlencode($savedId)),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    public function messages(): string
    {
        $locale = $this->request->getLocale();
        $data   = [
            'locale' => $locale,
            'slug'   => 'messages',
            'user'   => session()->get('user') ?? [],
        ];
        return view('admin_dashboard', $data);
    }

    /**
     * Fields of the profile form, grouped under the PROFILE_SECTIONS headings. Fields that are not editable are
     * disabled, so they are not submitted.
     */
    private function profileFields(): array
    {
        $config   = (new AuthMasterModel())->getConfiguration() + (new ClientMasterModel())->getConfiguration();
        $editable = array_merge(self::PROFILE_ACCOUNT_EDITABLE, self::PROFILE_CLIENT_EDITABLE);
        // the system-only options stay listed (disabled) so a current value still shows
        $config['otp_mode']['options'] = self::disableOptions($config['otp_mode']['options'], self::OTP_MODE_DISABLED);
        $config['display_name']['help'] = lang('Profile.help.display-name');
        // an array, as the pattern's | would split a rule string; the form checks it in the browser too
        $config['status_emoji']['rules']    = ['max_length[16]', 'regex_match[' . ClientMasterModel::EMOJI_PATTERN . ']'];
        $config['status_emoji']['messages'] = ['regex' => lang('Profile.error.emoji')];
        $config['status_emoji']['help']     = lang('Profile.help.status-emoji');
        // it picks the UI language, so only the languages the UI has are offered
        $config['preferred_language']['options']  = listSupportedLanguages();
        $config['preferred_language']['help']     = lang('Profile.help.preferred-language');
        $config['preferred_language']['messages'] = ['in_list' => lang('Profile.error.language')];

        $fields = [];
        foreach (self::PROFILE_SECTIONS as $heading => $names) {
            $fields[] = ['type' => 'heading', 'label' => lang('Profile.heading.' . $heading)];
            foreach ($names as $name) {
                $field = $config[$name];
                if (! in_array($name, $editable, true)) {
                    $field['disabled_in'] = ['create', 'edit'];
                }
                $fields[] = $field;
            }
        }
        return $fields;
    }

    /**
     * Fields of the create-business form, grouped under the BUSINESS_SECTIONS headings; no names in other
     * languages, as a new business has no web pages yet
     * @param array $types public_id => type name, for the Business Type dropdown
     * @param array $user  the session user, whose name and email start as the contact
     */
    private function businessFields(array $types, array $user): array
    {
        $config = (new BusinessMasterModel())->getConfiguration();
        // the type goes to the API by its public_id; the ID rules of business_type_id are the API's to check
        $config['business_type_public_id'] = [
            'name'    => 'business_type_public_id',
            'type'    => 'select',
            'label'   => lang('BusinessMaster.fields.business_type_id'),
            'options' => $types,
            'rules'   => 'required',
        ];
        // is_unique is checked by the API, which answers with the field's error
        $config['business_slug']['rules']    = 'required|max_length[128]|regex_match[' . BusinessMasterModel::SLUG_PATTERN . ']';
        $config['business_slug']['help']     = lang('CreateBusiness.help.business-slug');
        $config['business_slug']['messages'] = ['regex' => lang('CreateBusiness.error.business-slug')];
        $config['business_mood_and_tone']['help']   = lang('CreateBusiness.help.mood-and-tone');
        $this->audienceHelp($config);
        $this->chargeHelp($config);
        $this->bookingHelp($config);
        $this->cancelHelp($config);
        // no options until a country is picked: customScript fills them from admin/api/currencies and subdivisions
        $config['currency_code']['options']            = [];
        $config['contact_subdivision_code']['type']    = 'select';
        $config['contact_subdivision_code']['options'] = [];
        $config['contact_subdivision_code']['help']    = lang('CreateBusiness.help.subdivision');
        $config['country_code']['help']             = lang('CreateBusiness.help.fixed');
        $config['currency_code']['help']            = lang('CreateBusiness.help.fixed');
        $config['contact_person_name']['default']   = (string) ($user['full_name'] ?? '');
        $config['contact_email_address']['default'] = (string) ($user['email_address'] ?? '');

        $fields = [];
        foreach (self::BUSINESS_SECTIONS as $heading => $names) {
            $fields[] = ['type' => 'heading', 'label' => lang('CreateBusiness.heading.' . $heading)];
            foreach ($names as $name) {
                $fields[] = $config[$name];
            }
        }
        return $fields;
    }

    /**
     * The help of the audience fields, shared by the create- and edit-business forms
     * @param array $config BusinessMasterModel::getConfiguration(), changed in place
     */
    private function audienceHelp(array &$config): void
    {
        $config['lgbt_audience']['help']     = lang('CreateBusiness.help.audience');
        $config['naturist_audience']['help'] = lang('CreateBusiness.help.audience');
        $config['age_restricted']['help']    = lang('CreateBusiness.help.age-restricted');
    }

    /**
     * The help of the tip and service charge fields, shared by the create- and edit-business forms. Whether they fit
     * together (e.g. a required tip needs a minimum above 0) is the API's to check, which answers with the field's error.
     * @param array $config BusinessMasterModel::getConfiguration(), changed in place
     */
    private function chargeHelp(array &$config): void
    {
        $config['tip_policy']['help']           = lang('CreateBusiness.help.tip-policy');
        $config['tip_value']['help']            = lang('CreateBusiness.help.tip-value');
        $config['service_charge_value']['help'] = lang('CreateBusiness.help.service-charge');
        foreach (['tip_value', 'service_charge_value'] as $name) {
            $config[$name]['min'] = 0;
        }
    }

    /**
     * The help and bounds of the booking window fields, shared by the create- and edit-business forms. Whether the
     * notice fits the horizon is the API's to check. booking_notice_time is for a notice in days only: the form's
     * script (scripts/notice_time) hides and empties it otherwise.
     * @param array $config BusinessMasterModel::getConfiguration(), changed in place
     */
    private function bookingHelp(array &$config): void
    {
        $config['booking_horizon_days'] += ['min' => 1, 'max' => 365, 'help' => lang('CreateBusiness.help.booking-horizon')];
        $config['booking_notice_unit']['help']  = lang('CreateBusiness.help.booking-notice-unit');
        $config['booking_notice_value'] += ['min' => 0, 'help' => lang('CreateBusiness.help.booking-notice')];
        $config['booking_notice_time']['help']  = lang('CreateBusiness.help.booking-notice-time');
        $config['booking_notice_time']['optional'] = false; // shown only when it is needed
        $config['booking_notice_time']['view']  = static fn ($value) => null === $value || '' === $value ? '' : substr((string) $value, 0, 5);
    }

    /**
     * The help and bounds of the cancellation policy fields, shared by the create- and edit-business forms. Whether a
     * percentage fee is no more than 100 is the API's to check. cancel_notice_time is for a deadline in days only: the
     * form's script (scripts/notice_time) hides and empties it otherwise.
     * @param array $config BusinessMasterModel::getConfiguration(), changed in place
     */
    private function cancelHelp(array &$config): void
    {
        $config['cancel_notice_unit']['help']  = lang('CreateBusiness.help.cancel-notice-unit');
        $config['cancel_notice_value'] += ['min' => 0, 'help' => lang('CreateBusiness.help.cancel-notice')];
        $config['cancel_notice_time']['help']  = lang('CreateBusiness.help.cancel-notice-time');
        $config['cancel_notice_time']['optional'] = false; // shown only when it is needed
        $config['cancel_notice_time']['view']  = static fn ($value) => null === $value || '' === $value ? '' : substr((string) $value, 0, 5);
        $config['late_cancel_allowed']['help'] = lang('CreateBusiness.help.late-cancel-allowed');
        $config['late_cancel_fee_value'] += ['min' => 0, 'help' => lang('CreateBusiness.help.late-cancel-fee')];
        $config['no_show_fee_value'] += ['min' => 0, 'help' => lang('CreateBusiness.help.no-show-fee')];
    }

    /**
     * The business name in each language of its web pages (websiteLanguages()), optional, under their own heading:
     * sent as translations, kept in business_master_translation; none while the web pages have no languages
     */
    private function businessNameFields(): array
    {
        $languages = $this->websiteLanguages();
        if ([] === $languages) {
            return [];
        }
        $fields = [['type' => 'heading', 'label' => lang('CreateBusiness.heading.translations')]];
        foreach ($languages as $language => $label) {
            $fields[] = [
                'name'     => self::TRANSLATION_PREFIX . $language,
                'type'     => 'text',
                'label'    => lang('CreateBusiness.translation-label', [$label]),
                'rules'    => 'max_length[255]',
                'optional' => true,
            ];
        }
        return $fields;
    }

    /**
     * Table Builder config of myBusinesses(): no edit column, the Add button opens the create-business form
     */
    private function myBusinessesListConfig(): array
    {
        $roles    = lang('BusinessClientLink.enum.client_role');
        $defaults = lang('BusinessClientLink.enum.is_default_business');
        return [
            'table_id' => 'my-businesses-table',
            'title'    => lang('MyBusinesses.title'),
            'subtitle' => lang('MyBusinesses.subtitle'),
            'id_field' => 'business_slug',
            'editable' => false,
            'urls'     => [
                'data'   => base_url('admin/my-businesses/data'),
                'create' => base_url('admin/create-business'),
            ],
            'labels'   => ['add' => lang('MyBusinesses.add')],
            'messages' => ['loadFailed' => lang('MyBusinesses.load-failed')],
            'columns'  => [
                ['data' => 'business_name', 'title' => lang('MyBusinesses.column.business_name')],
                ['data' => 'country', 'title' => lang('MyBusinesses.column.country')],
                ['data' => 'client_role', 'title' => lang('MyBusinesses.column.client_role'), 'type' => 'badge', 'badges' => [
                    BusinessClientLinkModel::ROLE_OWNER    => ['label' => $roles[BusinessClientLinkModel::ROLE_OWNER], 'class' => 'bg-primary'],
                    BusinessClientLinkModel::ROLE_MANAGER  => ['label' => $roles[BusinessClientLinkModel::ROLE_MANAGER], 'class' => 'bg-info'],
                    BusinessClientLinkModel::ROLE_EMPLOYEE => ['label' => $roles[BusinessClientLinkModel::ROLE_EMPLOYEE], 'class' => 'bg-secondary'],
                ]],
                ['data' => 'is_default_business', 'title' => lang('MyBusinesses.column.is_default_business'), 'type' => 'badge', 'badges' => [
                    BusinessClientLinkModel::DEFAULT_YES => ['label' => $defaults[BusinessClientLinkModel::DEFAULT_YES], 'class' => 'bg-success'],
                    BusinessClientLinkModel::DEFAULT_NO  => ['label' => $defaults[BusinessClientLinkModel::DEFAULT_NO], 'class' => 'bg-secondary'],
                ]],
                ['data' => 'actions', 'title' => lang('MyBusinesses.column.actions'), 'type' => 'html', 'orderable' => false,
                    'searchable' => false, 'class' => 'text-end text-nowrap'],
            ],
            'order'    => [['business_name', 'asc']],
        ];
    }

    /**
     * The Actions cell of a row of myBusinessesData(): Make Default unless it is the default already, Switch unless
     * it is the session business (marked Current instead). Escaped here, as the column is inserted as HTML.
     */
    private function myBusinessActions(string $slug, string $name, bool $isCurrent, bool $isDefault): string
    {
        $button = static fn (string $action, string $class, string $icon, string $label) => '<button type="button" class="btn btn-sm ' . $class
            . ' ms-1" data-business-action="' . $action . '" data-business-slug="' . esc($slug, 'attr') . '" data-business-name="'
            . esc($name, 'attr') . '"><i class="bi ' . $icon . '"></i> ' . esc($label) . '</button>';
        $html = '';
        if (! $isDefault) {
            $html .= $button('make-default', 'btn-outline-secondary', 'bi-star', lang('MyBusinesses.make-default'));
        }
        if ($isCurrent) {
            $html .= '<span class="badge bg-primary ms-1">' . esc(lang('MyBusinesses.current')) . '</span>';
        } else {
            $html .= $button('switch', 'btn-outline-primary', 'bi-arrow-left-right', lang('MyBusinesses.switch'));
        }
        return $html;
    }

    /**
     * The business types for the Business Type dropdown, from LINK_INTERNAL_API v1/client/business-types (active
     * types only), named in the UI language
     * @return array public_id => type name
     */
    private function businessTypeOptions(string $locale): array
    {
        [$code, $body] = $this->callApi('v1/client/business-types', []);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException($body['message'] ?? lang('CreateBusiness.form.types-failed'));
        }
        $types = [];
        foreach ((array) ($body['data']['rows'] ?? []) as $row) {
            $names = (array) ($row['translations'] ?? []);
            $types[(string) $row['public_id']] = $names[$locale] ?? $names[BusinessTypeModel::DEFAULT_LANGUAGE] ?? (string) $row['type_code'];
        }
        asort($types, SORT_NATURAL | SORT_FLAG_CASE);
        return $types;
    }

    /**
     * Fetch a business of the signed-in client afresh through LINK_INTERNAL_API v1/client/business/switch, in the
     * shape sign-in gives it, and keep it as the session business
     * @param string|null $slug  null: the session business
     * @param array|null  $names set to its names in other languages (language_code => business_name), which are
     *                           not kept in the session; null when it cannot be fetched
     * @param array|null  $taxId set to ['tax_id' => its tax ID], which is not kept in the session either; null when
     *                           it cannot be fetched (or the API does not give it, e.g. to an employee)
     * @return array|null the session business; null when it cannot be fetched (the session is left as it is)
     */
    private function refreshSessionBusiness(?string $slug = null, ?array &$names = null, ?array &$taxId = null): ?array
    {
        $names = null;
        $taxId = null;
        $slug ??= currentBusinessSlug();
        if (null === $slug || '' === $slug) {
            return null;
        }
        [$code, $body] = $this->callApi('v1/client/business/switch', ['business_slug' => $slug]);
        $business = $body['data']['business'] ?? null;
        if ($code !== 200 || ($body['status'] ?? '') !== 'success' || ! is_array($business)) {
            return null;
        }
        session()->set('business', $business);
        $names = (array) ($body['data']['translations'] ?? []);
        if (array_key_exists('tax_id', (array) $body['data'])) {
            $taxId = ['tax_id' => $body['data']['tax_id']];
        }
        return $business;
    }

    /**
     * Where a business's store is found on LINK_MART: this followed by its business_slug
     */
    private function storeBaseUrl(): string
    {
        return rtrim((string) getenv('LINK_MART'), '/') . '/';
    }

    /**
     * Fields of the edit-business form, grouped under the BUSINESS_EDIT_SECTIONS headings. The country and the
     * currency are disabled, so they are shown but never submitted.
     * @param array  $types   public_id => type name, for the Business Type dropdown
     * @param string $country business_master.country_code, for the subdivisions and the telephone number
     * @param string $slug      the business slug now, for the store address in its help
     * @param bool   $withNames with the business name in each language of its web pages (left out when they could not be fetched,
     *                          so the names are not saved as empty)
     * @param bool   $withTaxId with the tax ID under the postal code (left out when it could not be fetched, likewise)
     */
    private function businessEditFields(array $types, string $country, string $slug, bool $withNames = true, bool $withTaxId = true): array
    {
        $config = (new BusinessMasterModel())->getConfiguration();
        $config['business_type_public_id'] = [
            'name'    => 'business_type_public_id',
            'type'    => 'select',
            'label'   => lang('BusinessMaster.fields.business_type_id'),
            'options' => $types,
            'rules'   => 'required',
        ];
        // is_unique is checked by the API, which answers with the field's error
        $config['business_slug']['rules']    = 'required|max_length[128]|regex_match[' . BusinessMasterModel::SLUG_PATTERN . ']';
        $config['business_slug']['help']     = lang('EditBusiness.help.business-slug', [$this->storeBaseUrl() . $slug]);
        $config['business_slug']['messages'] = ['regex' => lang('CreateBusiness.error.business-slug')];
        $config['business_mood_and_tone']['help'] = lang('CreateBusiness.help.mood-and-tone');
        $this->audienceHelp($config);
        $this->chargeHelp($config);
        $this->bookingHelp($config);
        $this->cancelHelp($config);
        foreach (BusinessMasterModel::FIXED_FIELDS as $name) {
            $config[$name]['disabled_in'] = ['create', 'edit'];
            $config[$name]['help']        = lang('EditBusiness.help.fixed');
            $config[$name]['optional']    = false;
            $config[$name]['rules']       = '';
        }
        $config['contact_subdivision_code']['type']    = 'select';
        $config['contact_subdivision_code']['options'] = listSupportedSubdivisions($country);
        $config['contact_phone_number']['country']     = $country;

        $fields = [];
        foreach (self::BUSINESS_EDIT_SECTIONS as $heading => $names) {
            $fields[] = ['type' => 'heading', 'label' => lang('CreateBusiness.heading.' . $heading)];
            foreach ($names as $name) {
                if ('tax_id' !== $name || $withTaxId) {
                    $fields[] = $config[$name];
                }
            }
        }
        return $withNames ? [...$fields, ...$this->businessNameFields()] : $fields;
    }

    /**
     * Fields of the store form, grouped under the STORE_SECTIONS headings, then the store name and address in each
     * language of the business's web pages (optional, sent as translations; none while it has no languages). The address heading is marked, so the form's script hides it with the
     * address fields for an online store.
     * @param string $country business_master.country_code of the session business
     */
    private function storeFields(string $country): array
    {
        $config = (new BusinessStoreModel())->getConfiguration();
        $config['store_type']['default']               = BusinessStoreModel::TYPE_PHYSICAL;
        $config['store_type']['help']                  = lang('Stores.help.store-type');
        $config['store_phone_number']['country']       = $country;
        // the store's opening hours are in it; most countries have one, Indonesia three
        $timezones                                     = listSupportedTimezones($country);
        $config['store_timezone']['options']           = $timezones;
        $config['store_timezone']['default']           = (string) array_key_first($timezones);
        $config['store_timezone']['help']              = lang('Stores.help.store-timezone');
        $config['store_subdivision_code']['type']      = 'select';
        $config['store_subdivision_code']['options']   = listSupportedSubdivisions($country);

        $fields = [];
        foreach (self::STORE_SECTIONS as $heading => $names) {
            if ('address' === $heading) {
                // a heading of its own, as the form builder's headings cannot be told apart
                $fields[] = ['type' => 'html', 'html' => '<div class="row mb-2 mt-4" data-store-address><div class="col-sm-8 offset-sm-4">'
                    . '<h6 class="text-uppercase text-muted mb-0">' . esc(lang('Stores.heading.address')) . '</h6></div></div>'];
            } else {
                $fields[] = ['type' => 'heading', 'label' => lang('Stores.heading.' . $heading)];
            }
            foreach ($names as $name) {
                $fields[] = $config[$name];
            }
        }
        // the name and address in each language of the web pages, a heading per language; the address hides with
        // the store's own for an online store
        foreach ($this->websiteLanguages() as $language => $label) {
            $fields[] = ['type' => 'heading', 'label' => lang('Stores.heading.translation', [$label])];
            foreach (self::STORE_TRANSLATION_FIELDS as $column => [$prefix, $maxLength]) {
                $fields[] = [
                    'name'     => $prefix . $language,
                    'type'     => 'text',
                    'label'    => lang('Stores.translation.' . $column, [$label]),
                    'rules'    => 'max_length[' . $maxLength . ']',
                    'optional' => true,
                ] + ('store_name' === $column ? ['help' => lang('Stores.help.translation-name')] : []);
            }
        }
        return $fields;
    }

    /**
     * The form fields of the store's address in the web pages' languages, which hide for an online store
     * @return list<string>
     */
    private function storeAddressTranslationNames(): array
    {
        $names = [];
        foreach (array_keys($this->websiteLanguages()) as $language) {
            foreach (self::STORE_TRANSLATION_FIELDS as $column => [$prefix]) {
                if ('store_name' !== $column) {
                    $names[] = $prefix . $language;
                }
            }
        }
        return $names;
    }

    /**
     * Form response of a failed store create or edit: the API names the errors of the texts in other languages
     * translations.<language>.<column> (translations.<language> for the language as a whole, put on its name), which
     * are renamed for the form before saveFailed() takes the others
     */
    private function storeFailed(int $code, array $body): ResponseInterface
    {
        $errors = [];
        foreach ((array) ($body['errors'] ?? []) as $key => $message) {
            if (preg_match('/^translations\.([^.]+)(?:\.([a-z0-9_]+))?$/', (string) $key, $m)) {
                $key = (self::STORE_TRANSLATION_FIELDS[$m[2] ?? ''] ?? self::STORE_TRANSLATION_FIELDS['store_name'])[0] . $m[1];
            }
            $errors[$key] = $message;
        }
        $body['errors'] = $errors;
        return $this->saveFailed($code, $body, lang('Stores.form.failed'));
    }

    /**
     * Table Builder config of stores(): each row's edit button opens the store, the Add button the store form
     */
    private function storesListConfig(): array
    {
        $config = (new BusinessStoreModel())->getConfiguration();
        return [
            'table_id' => 'stores-table',
            'title'    => lang('Stores.list.title'),
            'subtitle' => lang('Stores.list.subtitle', [(string) currentBusinessName()]),
            'id_field' => 'public_id',
            'urls'     => [
                'data'   => base_url('admin/business/stores/data'),
                'create' => base_url('admin/business/stores/create'),
                'edit'   => base_url('admin/business/stores/edit/{public_id}'),
            ],
            'labels'   => ['add' => lang('Stores.list.add')],
            'messages' => ['loadFailed' => lang('Stores.list.load-failed')],
            'columns'  => [
                ['data' => 'store_name', 'title' => lang('Stores.list.column.store_name')],
                ['data' => 'store_type', 'title' => lang('Stores.list.column.store_type'), 'type' => 'badge', 'badges' => $config['store_type']['badges']],
                ['data' => 'store_phone_number', 'title' => lang('Stores.list.column.store_phone_number'), 'orderable' => false],
                ['data' => 'location', 'title' => lang('Stores.list.column.location')],
            ],
            'order'    => [['store_name', 'asc']],
        ];
    }

    /**
     * The stores of the session business, from LINK_INTERNAL_API v1/client/stores
     * @return array{0: array, 1: string|null, 2: array} [stores, null, the whole data (plan_code, max_stores, can_add)]
     *         or [[], error message, []]
     */
    private function loadStores(): array
    {
        [$code, $body] = $this->callApi('v1/client/stores', ['business_slug' => (string) currentBusinessSlug()]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return [[], (string) ($body['message'] ?? lang('Stores.list.load-failed')), []];
        }
        $data = (array) ($body['data'] ?? []);
        return [(array) ($data['stores'] ?? []), null, $data];
    }

    /**
     * Why no store can be added: the plan's cap (Config\PlanLimits::STORES in the API) is reached
     * @return string '' when a store can be added, or the list failed to load (the API still refuses one over the cap)
     */
    private function storeCapReached(): string
    {
        [, $error, $data] = $this->loadStores();
        if (null !== $error || ($data['can_add'] ?? true)) {
            return '';
        }
        return lang('Stores.list.limit-reached', [(int) ($data['max_stores'] ?? 0), ucfirst((string) ($data['plan_code'] ?? ''))]);
    }

    /**
     * One store of the session business by its public_id (the API has no single-store call)
     * @return array|null null when the business has no such store
     * @throws RuntimeException when the stores cannot be loaded
     */
    private function findStore(string $publicId): ?array
    {
        [$stores, $error] = $this->loadStores();
        if (null !== $error) {
            throw new RuntimeException($error);
        }
        foreach ($stores as $store) {
            if ((string) ($store['public_id'] ?? '') === $publicId) {
                return (array) $store;
            }
        }
        return null;
    }

    /**
     * JSON answer of a failed make-default or switch call
     * @param int   $code HTTP status of the API call
     * @param array $body decoded API response
     */
    private function actionFailed(int $code, array $body): ResponseInterface
    {
        return $this->response->setStatusCode($code === 200 ? 502 : $code)->setJSON([
            'success'   => false,
            'message'   => $body['message'] ?? lang('MyBusinesses.action-failed'),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Turn value => label options into the form builder's list form, with the given values disabled
     * @param array $options value => label
     * @param array $disabled values that cannot be picked
     * @return array [['value', 'label', 'disabled'], ...]
     */
    private static function disableOptions(array $options, array $disabled): array
    {
        $out = [];
        foreach ($options as $value => $label) {
            $out[] = ['value' => $value, 'label' => $label, 'disabled' => in_array((string) $value, $disabled, true)];
        }
        return $out;
    }
}
