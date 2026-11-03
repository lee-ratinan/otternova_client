<?php

namespace App\Controllers;

use App\Models\AuthMasterModel;
use App\Models\ClientMasterModel;
use CodeIgniter\HTTP\ResponseInterface;
use CURLFile;
use RuntimeException;
use Throwable;

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
    private const PROFILE_ACCOUNT_EDITABLE = ['full_name', 'otp_mode'];
    private const PROFILE_CLIENT_EDITABLE  = ['legal_first_name', 'legal_middle_name', 'legal_last_name', 'display_name',
        'status_message', 'status_emoji', 'gender', 'date_of_birth', 'nationality_code', 'preferred_language', 'bio',
        'marketing_opt_in'];

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
            // a stand-alone upload form: the API saves the picture to avatar_path of auth_master row 'id'
            'extraForms' => [[
                'form_id'   => 'profile-picture-form',
                'title'     => lang('Profile.picture.title'),
                'subtitle'  => lang('Profile.picture.subtitle'),
                'id_prefix' => 'picture-',
                'id_field'  => 'id',
                'id'        => (int) ($user['id'] ?? 0),
                'urls'      => ['submit' => base_url('admin/image-upload'), 'delete' => base_url('admin/image-delete')],
                'values'    => ['used-for' => 'profile-picture', 'image-name' => $uid],
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

    public function myBusinesses(): string
    {
        $locale = $this->request->getLocale();
        $data   = [
            'locale' => $locale,
            'slug'   => 'my-businesses',
            'user'   => session()->get('user') ?? [],
        ];
        return view('admin_dashboard', $data);
    }

    public function notifications(): string
    {
        $locale = $this->request->getLocale();
        $data   = [
            'locale' => $locale,
            'slug'   => 'notifications',
            'user'   => session()->get('user') ?? [],
        ];
        return view('admin_dashboard', $data);
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

    /**
     * Form response of a failed API call
     * @param int    $code     HTTP status of the API call
     * @param array  $body     decoded API response
     * @param string $fallback message when the API gives none
     */
    private function saveFailed(int $code, array $body, string $fallback): ResponseInterface
    {
        // the API prefixes errors with their group (profile.status_emoji); the form only knows the field name
        $errors = [];
        foreach ((array) ($body['errors'] ?? []) as $key => $message) {
            $errors[preg_replace('/^.*\./', '', (string) $key)] = $message;
        }
        return $this->response->setStatusCode($code === 200 ? 422 : $code)->setJSON([
            'success'   => false,
            'message'   => $body['message'] ?? $fallback,
            'errors'    => $errors ?: null,
            'csrf_hash' => csrf_hash(), // keeps the form's token fresh when CSRF regenerates
        ]);
    }

    /**
     * POST to LINK_INTERNAL_API with the signed-in client's JWT and return [http status, decoded body]
     * @param bool $multipart true: sent as multipart/form-data (files as CURLFile), otherwise as JSON
     */
    private function callApi(string $endpoint, array $payload, bool $multipart = false): array
    {
        try {
            $response = service('curlrequest')->post(getenv('LINK_INTERNAL_API') . $endpoint, [
                ($multipart ? 'multipart' : 'json') => $payload,
                'headers'     => [
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . session()->get('access_token'),
                ],
                'timeout'     => 15,
                'http_errors' => false,
            ]);
            $code = $response->getStatusCode();
            $body = json_decode($response->getBody(), true);
            // a 404 without the API's own error body means the endpoint is missing, not the record
            if (! is_array($body) || $code >= 500 || ($code === 404 && ($body['status'] ?? '') !== 'error')) {
                log_message('error', 'LINK_INTERNAL_API ' . $endpoint . ' returned HTTP ' . $code);
                return [503, ['message' => lang('Login.error.service-unavailable')]];
            }
            return [$code, $body];
        } catch (Throwable $e) {
            log_message('error', 'LINK_INTERNAL_API ' . $endpoint . ' failed: ' . $e->getMessage());
            return [503, ['message' => lang('Login.error.service-unavailable')]];
        }
    }
}
