<?php

namespace App\Controllers;

use App\Models\AuthMasterModel;
use App\Models\BusinessClientLinkModel;
use App\Models\ClientMasterModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * The people of the session business: everyone in business_client_link, active and terminated, its owners included.
 * Its owners only (the routes' manager:owner filter, and v1/client/employee* in the API). A person comes in by their
 * auth_master.uid. The exception: publicProfiles() and publicProfile(), the read-only public profiles of the people
 * active in the business, open to anyone active in it (v1/client/colleague* in the API).
 *
 * What an owner sees and changes of an account (the API keeps the same lists, AuthAccountModel::OWNER_*):
 * - auth_master: email_address, telephone_number (the account holder's own to set), password_expiry, avatar,
 *   otp_mode and account_type are shown only; full_name is changed; account_status only from Suspended or Blocked
 *   back to Active, never on their own account (an active account is the person's in every business they work at:
 *   an owner takes them out of theirs by terminating the link); admin_notes is never shown
 * - client_master: the legal names, display_name and the ID document are changed; the rest (gender,
 *   date of birth, nationality, status, language, bio, marketing) is never shown
 * - business_client_link: role, status and dates are shown only; owner_notes, the business's own notes about the
 *   person, is changed (not client_master.internal_notes, which every business of theirs and Otternova staff share)
 */
class Employees extends BaseController
{
    private const ACCOUNT_SECTION  = ['email_address', 'telephone_number', 'password_expiry', 'full_name', 'otp_mode', 'account_status', 'account_type'];
    private const LINK_SECTION     = ['client_role', 'client_status', 'start_date', 'termination_date'];
    private const IDENTITY_SECTION = ['legal_first_name', 'legal_middle_name', 'legal_last_name', 'display_name', 'national_id_type', 'national_id_number'];
    private const NOTES_SECTION    = ['owner_notes'];

    /** What the form sends to v1/client/employee/edit; every other field is shown disabled and never forwarded */
    private const ACCOUNT_EDITABLE = ['full_name', 'account_status'];
    private const PROFILE_EDITABLE = ['legal_first_name', 'legal_middle_name', 'legal_last_name', 'display_name', 'national_id_type',
        'national_id_number'];
    private const LINK_EDITABLE    = ['owner_notes'];

    /**
     * The people of the session business (generic_list). Each row opens the person; the Add button is disabled
     * once the plan's cap of active people is reached (Config\PlanLimits::EMPLOYEES in the API).
     */
    public function index(): string
    {
        $data = [
            'locale'     => $this->request->getLocale(),
            'slug'       => 'employees',
            'user'       => (array) session('user'),
            'listConfig' => $this->listConfig() + ['add_disabled' => $this->capReached()],
        ];
        return view('generic_list', $data);
    }

    /**
     * DataTables data of index(), from LINK_INTERNAL_API v1/client/employees { business_slug }. A business has a
     * handful of people, so the API sends them all and the search, order and paging are done here.
     */
    public function data(): ResponseInterface
    {
        $config = $this->listConfig();
        $params = lb_request($this->request, $config);
        [$employees, $error] = $this->loadEmployees();
        if (null !== $error) {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, []) + ['error' => $error, 'csrf_hash' => csrf_hash()]);
        }

        $rows = [];
        foreach ($employees as $employee) {
            $phone  = (string) ($employee['telephone_number'] ?? '');
            $rows[] = [
                'uid'              => (string) ($employee['uid'] ?? ''),
                'full_name'        => (string) ($employee['full_name'] ?? ''),
                'email_address'    => (string) ($employee['email_address'] ?? ''),
                'telephone_number' => '' === $phone ? '' : formatPhoneNumber($phone),
                'account_status'   => (string) ($employee['account_status'] ?? ''),
                'client_role'      => (string) ($employee['client_role'] ?? ''),
                'client_status'    => (string) ($employee['client_status'] ?? ''),
            ];
        }
        $total = count($rows);
        // search and order by the label the owner sees, so a role or status is found by its name
        $labels = [
            'account_status' => lang('AuthMaster.enum.account_status'),
            'client_role'    => lang('BusinessClientLink.enum.client_role'),
            'client_status'  => lang('BusinessClientLink.enum.client_status'),
        ];
        $rows = $this->searchAndOrder($rows, $params, static fn (array $row, string $column) => isset($labels[$column])
            ? (string) ($labels[$column][$row[$column]] ?? $row[$column]) : (string) $row[$column]);
        $filtered = count($rows);
        $rows     = lb_rows(array_slice($rows, $params['start'], $params['length']), $config);

        return $this->response->setJSON(lb_response($params['draw'], $total, $filtered, $rows) + ['csrf_hash' => csrf_hash()]);
    }

    /**
     * The Add button's target: the owner looks an email address up (find()). An address with a client account is
     * added at once (add()), with a role and notes; one with none is invited (invite()), and the account they sign up
     * with through the emailed link is linked to the business. Below: the invitations still open. With the plan's cap
     * reached (open invitations count toward it), back to the list, whose Add button says why.
     */
    public function create(): string|RedirectResponse
    {
        [, $error, $data] = $this->loadEmployees();
        if (null === $error && ! ($data['can_add'] ?? true)) {
            return redirect()->to(base_url('admin/employees'));
        }
        $roles = (new BusinessClientLinkModel())->getConfiguration()['client_role'];
        $notes = ['name' => 'owner_notes', 'type' => 'textarea', 'rows' => 3, 'label' => lang('BusinessClientLink.fields.owner_notes'),
            'help' => lang('Employees.help.owner-notes'), 'optional' => true];
        $role  = ['name' => 'client_role', 'type' => 'select', 'label' => $roles['label'], 'options' => $roles['options'],
            'default' => BusinessClientLinkModel::ROLE_EMPLOYEE, 'rules' => 'required', 'help' => lang('Employees.add.role-help')];
        $person = static fn (string $kind): array => ['type' => 'html', 'html' => '<div class="row mb-3"><div class="col-sm-8 offset-sm-4" data-'
            . $kind . '-person></div></div>'];

        return view('generic_form', [
            'locale'      => $this->request->getLocale(),
            'slug'        => 'employee-create',
            'breadcrumbs' => [base_url('admin/employees') => lang('System.page.employees')],
            'user'        => (array) session('user'),
            'formConfig'  => [
                'form_id'  => 'find-employee-form',
                'title'    => lang('Employees.find.title'),
                'subtitle' => lang('Employees.find.subtitle', [(string) currentBusinessName()]),
                'mode'     => 'edit',
                'urls'     => ['submit' => base_url('admin/employees/find')],
                'labels'   => ['save' => lang('Employees.find.submit')],
                'fields'   => [['name' => 'email_address', 'type' => 'email', 'label' => lang('Employees.list.column.email_address'),
                    'rules' => 'required|valid_email|max_length[128]', 'autocomplete' => 'off']],
            ],
            'extraForms'  => [[
                'form_id'  => 'add-employee-form',
                'title'    => lang('Employees.add.title'),
                'subtitle' => lang('Employees.add.subtitle', [lang('Employees.add.today')]),
                'mode'     => 'create',
                'id_prefix' => 'add-',
                'urls'     => ['submit' => base_url('admin/employees/add')],
                'labels'   => ['save' => lang('Employees.add.submit')],
                'messages' => ['saved' => lang('Employees.add.done'), 'saveFailed' => lang('Employees.add.failed')],
                'fields'   => [['name' => 'email_address', 'type' => 'hidden'], $person('add'), $role, $notes],
            ], [
                'form_id'  => 'invite-employee-form',
                'title'    => lang('Employees.invite.title'),
                'subtitle' => lang('Employees.invite.subtitle'),
                'mode'     => 'create',
                'id_prefix' => 'invite-',
                'urls'     => ['submit' => base_url('admin/employees/invite')],
                'labels'   => ['save' => lang('Employees.invite.submit')],
                'messages' => ['saved' => lang('Employees.invite.done'), 'saveFailed' => lang('Employees.invite.failed')],
                'fields'   => [['name' => 'email_address', 'type' => 'hidden'], $person('invite'), $role, $notes],
            ]],
            'extraHtml'    => view('employees/invitations', ['invitations' => (array) ($data['invitations'] ?? []), 'roles' => $roles['options']]),
            'customScript' => view('scripts/add_employee', [
                'urls'     => ['find' => base_url('admin/employees/find')],
                'messages' => [
                    'add'      => lang('Employees.find.add'),
                    'invite'   => lang('Employees.find.invite'),
                    'active'   => lang('Employees.find.active'),
                    'former'   => lang('Employees.find.former'),
                    'open'     => lang('Employees.find.open'),
                    'failed'   => lang('Employees.find.failed'),
                ],
            ]),
        ]);
    }

    /**
     * Look an email address up for create(), through LINK_INTERNAL_API v1/client/employee/find
     * { business_slug, email_address }: {success, found, account: {full_name, email_address, avatar}, client_status,
     * url (their page, when they were ever linked here)}
     */
    public function find(): ResponseInterface
    {
        [$code, $body] = $this->callApi('v1/client/employee/find', [
            'business_slug' => (string) currentBusinessSlug(),
            'email_address' => trim((string) $this->request->getPost('email_address')),
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, lang('Employees.find.failed'));
        }
        $data    = (array) ($body['data'] ?? []);
        $account = (array) ($data['account'] ?? []);
        $status  = $data['client_status'] ?? null;
        return $this->response->setJSON([
            'success'       => true,
            'found'         => (bool) ($data['found'] ?? false),
            'account'       => [] === $account ? null : [
                'full_name'     => (string) ($account['full_name'] ?? ''),
                'email_address' => (string) ($account['email_address'] ?? ''),
                'avatar'        => avatarUrl($account['avatar_path'] ?? null),
            ],
            'client_status' => $status,
            'url'           => null === $status ? null : base_url('admin/employees/edit/' . rawurlencode((string) ($account['uid'] ?? ''))),
            'csrf_hash'     => csrf_hash(),
        ]);
    }

    /**
     * Add the person with a client account to the session business through LINK_INTERNAL_API v1/client/employee/add
     * { business_slug, email_address, client_role, owner_notes }, starting today; then their page opens
     */
    public function add(): ResponseInterface
    {
        [$code, $body] = $this->callApi('v1/client/employee/add', ['business_slug' => (string) currentBusinessSlug()] + $this->addPayload());
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, lang('Employees.add.failed'));
        }
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('Employees.add.done'),
            'redirect'  => base_url('admin/employees/edit/' . rawurlencode((string) ($body['data']['uid'] ?? ''))),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Invite someone with no account to the session business through LINK_INTERNAL_API v1/client/employee/invite
     * { business_slug, email_address, client_role, owner_notes }; the API emails them the link
     */
    public function invite(): ResponseInterface
    {
        [$code, $body] = $this->callApi('v1/client/employee/invite', ['business_slug' => (string) currentBusinessSlug()] + $this->addPayload());
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, lang('Employees.invite.failed'));
        }
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('Employees.invite.done'),
            'redirect'  => base_url('admin/employees/create'), // listed there among the open invitations
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * What add() and invite() forward: the email address looked up, the role and the notes
     */
    private function addPayload(): array
    {
        return [
            'email_address' => trim((string) $this->request->getPost('email_address')),
            'client_role'   => (string) $this->request->getPost('client_role'),
            'owner_notes'   => (string) $this->request->getPost('owner_notes'),
        ];
    }

    /**
     * One person of the session business, from LINK_INTERNAL_API v1/client/employee { business_slug, uid }, opened in
     * view mode; the Edit button switches the page to edit mode
     * @param string $uid auth_master.uid
     */
    public function form(string $uid): string
    {
        [$code, $body] = $this->callApi('v1/client/employee', ['business_slug' => (string) currentBusinessSlug(), 'uid' => $uid]);
        if (404 === $code) {
            throw PageNotFoundException::forPageNotFound(lang('Employees.form.not-found'));
        }
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException($body['message'] ?? lang('Employees.form.load-failed'));
        }
        $data    = (array) ($body['data'] ?? []);
        $account = (array) ($data['account'] ?? []);
        // keyed by field name, as the form looks values up by the field's 'name'
        $values  = $account + (array) ($data['profile'] ?? []) + (array) ($data['link'] ?? []);
        $name    = (string) ($account['full_name'] ?? $uid);
        $statuses = array_map('strval', (array) ($data['status_options'] ?? []));

        $page = [
            'locale'      => $this->request->getLocale(),
            'slug'        => 'employees',
            'page_title'  => $name,
            'breadcrumbs' => [base_url('admin/employees') => lang('System.page.employees')],
            'user'        => (array) session('user'),
            'formConfig'  => [
                'form_id'  => 'employee-form',
                'title'    => lang('Employees.form.title'),
                'subtitle' => lang('Employees.form.subtitle', [(string) currentBusinessName()]),
                'mode'     => 'view',
                'id_field' => 'uid',
                'id'       => $uid,
                'locale'   => $this->request->getLocale(),
                'urls'     => [
                    'submit' => base_url('admin/employees/save'),
                    'edit'   => base_url('admin/employees/edit/' . rawurlencode($uid)),
                    'list'   => base_url('admin/employees'),
                ],
                'messages' => ['saved' => lang('Employees.form.saved'), 'saveFailed' => lang('Employees.form.failed')],
                'values'   => $values,
                'fields'   => $this->formFields((string) ($account['avatar_path'] ?? ''), $statuses, (bool) ($data['is_self'] ?? false)),
            ],
        ];
        return view('generic_form', $page + $this->linkActions($uid, $name, $data));
    }

    /**
     * Save a person of the session business through LINK_INTERNAL_API v1/client/employee/edit
     * { business_slug, uid, account, profile, link }: only the editable fields are forwarded, and the API checks them again
     */
    public function save(): ResponseInterface
    {
        $post    = (array) $this->request->getPost();
        $uid     = trim((string) ($post['uid'] ?? ''));
        $account = array_intersect_key($post, array_flip(self::ACCOUNT_EDITABLE));
        $profile = array_intersect_key($post, array_flip(self::PROFILE_EDITABLE));
        $link    = array_intersect_key($post, array_flip(self::LINK_EDITABLE));

        [$code, $body] = $this->callApi('v1/client/employee/edit', [
            'business_slug' => (string) currentBusinessSlug(),
            'uid'           => $uid,
            'account'       => (object) $account,
            'profile'       => (object) $profile,
            'link'          => (object) $link,
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, lang('Employees.form.failed'));
        }

        // the owner's own name shows in the layout, from the session user
        $user = (array) session('user');
        if ($uid === (string) ($user['uid'] ?? '') && isset($account['full_name'])) {
            session()->set('user', ['full_name' => trim((string) $account['full_name'])] + $user);
        }
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('Employees.form.saved'),
            'redirect'  => base_url('admin/employees/edit/' . rawurlencode($uid)),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * What can be done to a person's employment, below their form (generic_form's extraForms / extraHtml / customScript):
     * - someone else still working here: End Employment (their link ends, their account stays)
     * - the owner themselves: Leave Business, once another active owner can take over; else a note saying why not
     * - someone whose employment ended: Rehire, while the plan's cap leaves room; else a note saying why not
     * @param string $uid  auth_master.uid
     * @param string $name their full name
     * @param array  $data v1/client/employee's data
     */
    private function linkActions(string $uid, string $name, array $data): array
    {
        $link   = (array) ($data['link'] ?? []);
        $isSelf = (bool) ($data['is_self'] ?? false);
        if (BusinessClientLinkModel::STATUS_ACTIVE === ($link['client_status'] ?? null)) {
            $key = $isSelf ? 'Employees.leave' : 'Employees.terminate';
            if ($isSelf && ! ($data['can_leave'] ?? false)) {
                return ['extraHtml' => view('employees/notice_card', ['title' => lang('Employees.leave.title'), 'text' => lang('Employees.leave.only-owner')])];
            }
            return [
                'extraForms'   => [$this->terminateForm($uid, (string) ($link['start_date'] ?? ''), $key)],
                'customScript' => view('scripts/terminate_employee', [
                    'formId'  => 'terminate-form',
                    'confirm' => [
                        'title'   => lang($key . '.confirm-title'),
                        'text'    => lang($key . '.confirm-text', [$name, '{0}', (string) currentBusinessName()]),
                        'confirm' => lang($key . '.submit'),
                        'cancel'  => lang('Employees.terminate.cancel'),
                    ],
                ]),
            ];
        }
        $cap = (array) ($data['rehire'] ?? []);
        if (! ($cap['can_add'] ?? true)) {
            return ['extraHtml' => view('employees/notice_card', ['title' => lang('Employees.rehire.title'),
                'text' => lang('Employees.list.limit-reached', [(int) ($cap['max_employees'] ?? 0), ucfirst((string) ($cap['plan_code'] ?? ''))])])];
        }
        return ['extraForms' => [[
            'form_id'   => 'rehire-form',
            'title'     => lang('Employees.rehire.title'),
            'subtitle'  => lang('Employees.rehire.subtitle', [$name]),
            'mode'      => 'edit',
            'id_prefix' => 'rehire-',
            'id_field'  => 'uid',
            'id'        => $uid,
            'urls'      => ['submit' => base_url('admin/employees/rehire')],
            'labels'    => ['save' => lang('Employees.rehire.submit')],
            'messages'  => ['saved' => lang('Employees.rehire.done'), 'saveFailed' => lang('Employees.rehire.failed')],
            'fields'    => [],
        ]]];
    }

    /**
     * Take a person whose employment ended back through LINK_INTERNAL_API v1/client/employee/rehire
     * { business_slug, uid }: their link is active again, with no termination date
     */
    public function rehire(): ResponseInterface
    {
        $uid = trim((string) $this->request->getPost('uid'));
        [$code, $body] = $this->callApi('v1/client/employee/rehire', ['business_slug' => (string) currentBusinessSlug(), 'uid' => $uid]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, lang('Employees.rehire.failed'));
        }
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('Employees.rehire.done'),
            'redirect'  => base_url('admin/employees/edit/' . rawurlencode($uid)),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * End the employment of a person of the session business through LINK_INTERNAL_API v1/client/employee/terminate
     * { business_slug, uid, termination_date }: their business_client_link becomes Terminated, their account stays.
     * An owner ending their own (leaving the business) is no longer in it: the session moves to their default business
     * (else the first left), or, with none, to creating one.
     */
    public function terminate(): ResponseInterface
    {
        $uid    = trim((string) $this->request->getPost('uid'));
        $isSelf = $uid === (string) (session('user')['uid'] ?? '');
        $left   = (string) currentBusinessName();
        [$code, $body] = $this->callApi('v1/client/employee/terminate', [
            'business_slug'    => (string) currentBusinessSlug(),
            'uid'              => $uid,
            'termination_date' => trim((string) $this->request->getPost('termination_date')),
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, lang('Employees.terminate.failed'));
        }
        if ($isSelf) {
            $this->moveSessionToAnotherBusiness();
            return $this->response->setJSON([
                'success'   => true,
                'message'   => lang('Employees.leave.done', [$left]),
                'redirect'  => base_url('admin/dashboard'), // with no business left, AuthFilter sends them on to create one
                'csrf_hash' => csrf_hash(),
            ]);
        }
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('Employees.terminate.done'),
            'redirect'  => base_url('admin/employees/edit/' . rawurlencode($uid)),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * After the owner left the session business: the session takes the business sign-in would open now (their default,
     * else the first; LINK_INTERNAL_API v1/client/businesses lists the default first), as v1/client/business/switch
     * gives it; with none, no business, so AuthFilter sends them to create one
     */
    private function moveSessionToAnotherBusiness(): void
    {
        [$code, $body] = $this->callApi('v1/client/businesses', []);
        $next = $code === 200 ? (string) (((array) ($body['data']['businesses'] ?? []))[0]['business_slug'] ?? '') : '';
        if ('' !== $next) {
            [$code, $body] = $this->callApi('v1/client/business/switch', ['business_slug' => $next]);
            $business = $body['data']['business'] ?? null;
            if ($code === 200 && is_array($business)) {
                session()->set('business', $business);
                return;
            }
        }
        session()->set('business', null);
    }

    /**
     * The stand-alone form below the employee form that ends an employment (or, for the owner themselves, leaves the
     * business): the termination date, today at first, not before their start date nor after today
     * @param string $uid       auth_master.uid
     * @param string $startDate business_client_link.start_date (Y-m-d)
     * @param string $key       its texts: Employees.terminate, or Employees.leave for the owner themselves
     */
    private function terminateForm(string $uid, string $startDate, string $key = 'Employees.terminate'): array
    {
        $today = date('Y-m-d');
        return [
            'form_id'   => 'terminate-form',
            'title'     => lang($key . '.title'),
            'subtitle'  => lang($key . '.subtitle'),
            'mode'      => 'edit',
            'id_prefix' => 'terminate-',
            'id_field'  => 'uid',
            'id'        => $uid,
            'urls'      => ['submit' => base_url('admin/employees/terminate')],
            'labels'    => ['save' => lang($key . '.submit')],
            'messages'  => ['saved' => lang('Employees.terminate.done'), 'saveFailed' => lang('Employees.terminate.failed')],
            'values'    => ['termination_date' => $today],
            'fields'    => [
                ['name' => 'termination_date', 'type' => 'date', 'label' => lang('BusinessClientLink.fields.termination_date'),
                 'rules' => 'required|valid_date[Y-m-d]', 'min' => $startDate, 'max' => $today],
            ],
        ];
    }

    /**
     * Table Builder config of index(): each row's button opens the person, the Add button the (future) create form
     */
    private function listConfig(): array
    {
        $account = (new AuthMasterModel())->getConfiguration();
        $link    = (new BusinessClientLinkModel())->getConfiguration();
        return [
            'table_id' => 'employees-table',
            'title'    => lang('Employees.list.title'),
            'subtitle' => lang('Employees.list.subtitle', [(string) currentBusinessName()]),
            'id_field' => 'uid',
            'urls'     => [
                'data'   => base_url('admin/employees/data'),
                'create' => base_url('admin/employees/create'),
                'edit'   => base_url('admin/employees/edit/{uid}'),
            ],
            'labels'   => ['add' => lang('Employees.list.add')],
            'messages' => ['loadFailed' => lang('Employees.list.load-failed')],
            'columns'  => [
                ['data' => 'full_name', 'title' => lang('Employees.list.column.full_name')],
                ['data' => 'email_address', 'title' => lang('Employees.list.column.email_address')],
                ['data' => 'telephone_number', 'title' => lang('Employees.list.column.telephone_number'), 'orderable' => false],
                ['data' => 'account_status', 'title' => lang('Employees.list.column.account_status'), 'type' => 'badge', 'badges' => $account['account_status']['badges']],
                ['data' => 'client_role', 'title' => lang('Employees.list.column.client_role'), 'type' => 'badge', 'badges' => $link['client_role']['badges']],
                ['data' => 'client_status', 'title' => lang('Employees.list.column.client_status'), 'type' => 'badge', 'badges' => $link['client_status']['badges']],
            ],
            'order'    => [['full_name', 'asc']],
        ];
    }

    /**
     * The people of the session business, from LINK_INTERNAL_API v1/client/employees
     * @return array{0: array, 1: string|null, 2: array} [employees, null, the whole data (plan_code, max_employees,
     *         active_employees, can_add)] or [[], error message, []]
     */
    private function loadEmployees(): array
    {
        [$code, $body] = $this->callApi('v1/client/employees', ['business_slug' => (string) currentBusinessSlug()]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return [[], (string) ($body['message'] ?? lang('Employees.list.load-failed')), []];
        }
        $data = (array) ($body['data'] ?? []);
        return [(array) ($data['employees'] ?? []), null, $data];
    }

    /**
     * Why no employee can be added: the plan's cap of active people (Config\PlanLimits::EMPLOYEES in the API) is reached
     * @return string '' when one can be added, or the list failed to load (the API will still refuse one over the cap)
     */
    private function capReached(): string
    {
        [, $error, $data] = $this->loadEmployees();
        if (null !== $error || ($data['can_add'] ?? true)) {
            return '';
        }
        return lang('Employees.list.limit-reached', [(int) ($data['max_employees'] ?? 0), ucfirst((string) ($data['plan_code'] ?? ''))]);
    }

    /**
     * Fields of the employee form, under its section headings. Fields that are not editable are disabled, so they
     * are not submitted.
     * @param string   $avatar   auth_master.avatar_path ('' for none)
     * @param string[] $statuses the account_status values the owner may save (the current one first)
     * @param bool     $isSelf   the owner's own account, whose status they cannot change
     */
    private function formFields(string $avatar, array $statuses, bool $isSelf): array
    {
        $config = (new AuthMasterModel())->getConfiguration() + (new ClientMasterModel())->getConfiguration()
            + (new BusinessClientLinkModel())->getConfiguration();
        $editable = array_merge(self::ACCOUNT_EDITABLE, self::PROFILE_EDITABLE, self::LINK_EDITABLE);

        // the statuses the owner may not move to stay listed (disabled), so the current one always shows
        $config['account_status']['options'] = array_map(static fn ($value, $label) => [
            'value' => $value, 'label' => $label, 'disabled' => ! in_array((string) $value, $statuses, true),
        ], array_keys($config['account_status']['options']), $config['account_status']['options']);
        $config['account_status']['help'] = lang($isSelf ? 'Employees.help.own-status' : 'Employees.help.account-status');
        $config['telephone_number']['help'] = lang('Employees.help.telephone');
        $config['owner_notes']['help']      = lang('Employees.help.owner-notes');

        $avatarRow = '<div class="row mb-3"><div class="col-sm-4 text-sm-end">' . esc(lang('AuthMaster.fields.avatar_path')) . '</div>'
            . '<div class="col-sm-8"><img src="' . esc(avatarUrl($avatar), 'attr') . '" alt="" class="rounded-circle" width="64" height="64"></div></div>';

        $fields = [];
        foreach (['account' => self::ACCOUNT_SECTION, 'employment' => self::LINK_SECTION, 'identity' => self::IDENTITY_SECTION,
                  'notes' => self::NOTES_SECTION] as $heading => $names) {
            $fields[] = ['type' => 'heading', 'label' => lang('Employees.heading.' . $heading)];
            if ('account' === $heading) {
                $fields[] = ['type' => 'html', 'html' => $avatarRow];
            }
            foreach ($names as $name) {
                $field = $config[$name];
                // a status with nowhere to go is shown, not picked
                $locked = ! in_array($name, $editable, true) || ('account_status' === $name && count($statuses) < 2);
                if ($locked) {
                    $field['disabled_in'] = ['create', 'edit'];
                    $field['rules']       = '';
                }
                $fields[] = $field;
            }
        }
        return $fields;
    }

    /**
     * The people active in the session business, so colleagues can know each other: a grid of their photos with the
     * name under each, opening their public profile. From LINK_INTERNAL_API v1/client/colleagues { business_slug },
     * which sends only the people active there, by name. Anyone active in the business sees it, whatever their role.
     */
    public function publicProfiles(): string
    {
        [$code, $body] = $this->callApi('v1/client/colleagues', ['business_slug' => (string) currentBusinessSlug()]);
        $error  = $code !== 200 || ($body['status'] ?? '') !== 'success' ? (string) ($body['message'] ?? lang('Employees.public.load-failed')) : null;
        $people = [];
        foreach (null === $error ? (array) ($body['data']['colleagues'] ?? []) : [] as $colleague) {
            $uid      = (string) ($colleague['uid'] ?? '');
            $people[] = [
                'name'   => trim((string) ($colleague['display_name'] ?? '')) ?: (string) ($colleague['full_name'] ?? ''),
                'avatar' => avatarUrl($colleague['avatar_path'] ?? null),
                'url'    => base_url('admin/public-profile/' . rawurlencode($uid)),
            ];
        }
        return view('employees/public_profiles', [
            'locale'       => $this->request->getLocale(),
            'slug'         => 'public-profiles',
            'user'         => (array) session('user'),
            'people'       => $people,
            'error'        => $error,
            'businessName' => (string) currentBusinessName(),
        ]);
    }

    /**
     * The public profile of someone active in the session business, from LINK_INTERNAL_API v1/client/colleague
     * { business_slug, uid }; the API answers 404 for anyone not working there (never, or no longer). Read only.
     * @param string $uid auth_master.uid
     */
    public function publicProfile(string $uid): string
    {
        [$code, $body] = $this->callApi('v1/client/colleague', ['business_slug' => (string) currentBusinessSlug(), 'uid' => $uid]);
        if (404 === $code) {
            throw PageNotFoundException::forPageNotFound(lang('Employees.public.not-found'));
        }
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException($body['message'] ?? lang('Employees.public.load-failed'));
        }
        $data     = (array) ($body['data'] ?? []);
        $account  = (array) ($data['account'] ?? []);
        $profile  = (array) ($data['profile'] ?? []);
        $link     = (array) ($data['link'] ?? []);
        $locale   = $this->request->getLocale();
        $fullName = (string) ($account['full_name'] ?? '');
        $phone    = (string) ($account['telephone_number'] ?? '');
        $birth    = (string) ($profile['date_of_birth'] ?? '');
        $start    = (string) ($link['start_date'] ?? '');

        $person = [
            'name'           => trim((string) ($profile['display_name'] ?? '')) ?: $fullName,
            'full_name'      => $fullName,
            'avatar'         => avatarUrl($account['avatar_path'] ?? null),
            'status_emoji'   => (string) ($profile['status_emoji'] ?? ''),
            'status_message' => (string) ($profile['status_message'] ?? ''),
            'bio'            => (string) ($profile['bio'] ?? ''),
            'role'           => (string) (lang('BusinessClientLink.enum.client_role')[$link['client_role'] ?? ''] ?? ''),
            'start_date'     => '' === $start ? '' : (string) formatDate($start, $locale),
            'email_address'  => (string) ($account['email_address'] ?? ''),
            'telephone'      => '' === $phone ? '' : formatPhoneNumber($phone),
            'telephone_e164' => $phone,
            'gender'         => (string) (lang('ClientMaster.enum.gender')[$profile['gender'] ?? ''] ?? ''),
            'date_of_birth'  => '' === $birth ? '' : (string) formatDate($birth, $locale),
            'nationality'    => (string) (listCountries()[$profile['nationality_code'] ?? ''] ?? ''),
            'language'       => (string) (listLanguages()[$profile['preferred_language'] ?? ''] ?? ''),
            'is_self'        => (bool) ($data['is_self'] ?? false),
        ];

        return view('admin_public_profile', [
            'locale'       => $locale,
            'slug'         => 'public-profiles',
            'user'         => (array) session('user'),
            'page_title'   => esc($person['name']),
            'breadcrumbs'  => [base_url('admin/public-profiles') => lang('System.page.public-profiles')],
            'person'       => $person,
            'businessName' => (string) currentBusinessName(),
        ]);
    }
}
