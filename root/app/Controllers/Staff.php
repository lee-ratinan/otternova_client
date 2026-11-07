<?php

namespace App\Controllers;

use App\Models\BusinessClientLinkModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * Where the people of the session business work and what they are paid: the stores they work in, the services they
 * may perform and their commission on them, their salary history; and how the business pays commission. Its owners
 * and managers (the routes' manager filter, and v1/client/staff* and v1/client/commission-policy* in the API).
 * A person comes in by their auth_master.uid. People are added and their accounts managed on the Employees pages,
 * which are the owners' only.
 */
class Staff extends BaseController
{
    /** Name of the services editor's inputs: services[<public_id>][assigned|commission_value|commission_unit] */
    private const SERVICES_FIELD = 'services';

    /** The fields of a salary, as v1/client/staff/salary/add and salary/edit take them */
    private const SALARY_FIELDS = ['salary_amount', 'salary_period', 'effective_date', 'end_date'];

    /** The fields of the commission settings, as v1/client/commission-policy/save takes them */
    private const POLICY_FIELDS = ['commission_status', 'commission_period', 'commission_threshold'];

    /** salary_period and commission_period */
    private const PERIODS = ['W', 'S', 'M'];

    /**
     * The people of the session business (generic_list), each with how many stores and services they have and their
     * salary today. Each row opens the person; people are added on the Employees pages.
     */
    public function index(): string
    {
        return view('generic_list', [
            'locale'     => $this->request->getLocale(),
            'slug'       => 'staff',
            'user'       => (array) session('user'),
            'listConfig' => $this->listConfig(),
        ]);
    }

    /**
     * DataTables data of index(), from LINK_INTERNAL_API v1/client/staff { business_slug }. A business has a handful
     * of people, so the API sends them all and the search, order and paging are done here.
     */
    public function data(): ResponseInterface
    {
        $config = $this->listConfig();
        $params = lb_request($this->request, $config);
        [$code, $body] = $this->callApi('v1/client/staff', ['business_slug' => (string) currentBusinessSlug()]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, []) + ['error' => $body['message'] ?? lang('Staff.list.load-failed'), 'csrf_hash' => csrf_hash()]);
        }
        $currency = (string) ($body['data']['currency_code'] ?? '');
        $rows     = [];
        foreach ((array) ($body['data']['staff'] ?? []) as $person) {
            $rows[] = [
                'uid'           => (string) ($person['uid'] ?? ''),
                'full_name'     => (string) ($person['full_name'] ?? ''),
                'client_role'   => (string) ($person['client_role'] ?? ''),
                'client_status' => (string) ($person['client_status'] ?? ''),
                'store_count'   => (int) ($person['store_count'] ?? 0),
                'service_count' => (int) ($person['service_count'] ?? 0),
                'salary'        => null === ($person['salary_amount'] ?? null) ? ''
                    : lang('Staff.salary.per', [formatMoney($person['salary_amount'], $currency), lang('Staff.period.' . $person['salary_period'])]),
            ];
        }
        $total  = count($rows);
        $labels = ['client_role' => lang('BusinessClientLink.enum.client_role'), 'client_status' => lang('BusinessClientLink.enum.client_status')];
        $rows   = $this->searchAndOrder($rows, $params, static fn (array $row, string $column) => isset($labels[$column])
            ? (string) ($labels[$column][$row[$column]] ?? $row[$column]) : (string) $row[$column]);
        $filtered = count($rows);
        $rows     = lb_rows(array_slice($rows, $params['start'], $params['length']), $config);

        return $this->response->setJSON(lb_response($params['draw'], $total, $filtered, $rows) + ['csrf_hash' => csrf_hash()]);
    }

    /**
     * One person's page: the stores they work in, the services they perform with their commission and their own
     * commission threshold, their latest salary (to correct it) and the form to change it, above the salary history
     * @param string $uid auth_master.uid
     */
    public function form(string $uid): string
    {
        [$code, $body] = $this->callApi('v1/client/staff/show', ['business_slug' => (string) currentBusinessSlug(), 'uid' => $uid]);
        if ($code === 404) {
            throw PageNotFoundException::forPageNotFound((string) ($body['message'] ?? lang('Staff.form.not-found')));
        }
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException((string) ($body['message'] ?? lang('Staff.list.load-failed')));
        }
        $data     = (array) $body['data'];
        $person   = (array) $data['person'];
        $policy   = (array) $data['commission'];
        $currency = (string) $data['currency_code'];
        $salaries = (array) ($data['salaries'] ?? []);
        $locale   = $this->request->getLocale();
        $name     = (string) $person['full_name'];
        $periods  = $this->periodOptions();
        $hidden   = ['name' => 'uid', 'type' => 'hidden', 'default' => $uid];
        $role     = lang('BusinessClientLink.enum.client_role')[$person['client_role']] ?? $person['client_role'];

        $stores = [];
        foreach ((array) $data['stores'] as $store) {
            $stores[(string) $store['public_id']] = (string) (($store['translations'] ?? [])[$locale] ?? $store['store_name']);
        }
        $assignedStores = array_values(array_map(static fn (array $s) => (string) $s['public_id'], array_filter((array) $data['stores'], static fn (array $s) => ! empty($s['assigned']))));

        $salaryFields = fn (bool $edit): array => [
            ['name' => 'salary_amount', 'type' => 'number', 'label' => lang('Staff.salary.amount', [$currency]), 'rules' => 'required|numeric|greater_than_equal_to[0]',
                'step' => '0.01', 'min' => 0],
            ['name' => 'salary_period', 'type' => 'select', 'label' => lang('Staff.salary.period'), 'options' => $periods, 'rules' => 'required', 'default' => 'M'],
            ['name' => 'effective_date', 'type' => 'date', 'label' => lang('Staff.salary.effective-date'), 'rules' => 'required',
                'help' => $edit ? null : lang('Staff.salary.effective-help')],
            ['name' => 'end_date', 'type' => 'date', 'label' => lang('Staff.salary.end-date'), 'optional' => true, 'help' => lang('Staff.salary.end-help')],
            $hidden,
        ];
        $history = ['type' => 'html', 'html' => view('staff/salary_history', ['salaries' => $salaries, 'currency' => $currency, 'locale' => $locale])];

        $forms = [[
            'form_id'   => 'staff-services-form',
            'title'     => lang('Staff.services.title'),
            'subtitle'  => lang('Staff.services.subtitle'),
            'mode'      => 'edit',
            'id_prefix' => 'services-',
            'locale'    => $locale,
            'urls'      => ['submit' => base_url('admin/staff/services/save')],
            'messages'  => ['saved' => lang('Staff.services.saved'), 'saveFailed' => lang('Staff.form.failed')],
            'values'    => ['commission_threshold' => $person['commission_threshold'], 'uid' => $uid],
            'fields'    => [
                ['type' => 'html', 'html' => view('staff/services_editor', ['services' => (array) $data['services'], 'field' => self::SERVICES_FIELD,
                    'currency' => $currency, 'locale' => $locale])],
                ['type' => 'heading', 'label' => lang('Staff.services.threshold-heading')],
                ['name' => 'commission_threshold', 'type' => 'number', 'label' => lang('Staff.services.threshold', [$currency]), 'optional' => true,
                    'rules' => 'permit_empty|numeric|greater_than_equal_to[0]', 'step' => '0.01', 'min' => 0,
                    'help' => lang('Staff.services.threshold-help', [formatMoney($policy['commission_threshold'], $currency),
                        lang('Staff.period-of.' . $policy['commission_period'])])
                        . ('A' === $policy['commission_status'] ? '' : ' ' . lang('Staff.services.commission-off'))],
                $hidden,
            ],
        ]];
        if ([] !== $salaries) {
            $forms[] = [
                'form_id'   => 'staff-salary-edit-form',
                'title'     => lang('Staff.salary.edit-title'),
                'subtitle'  => lang('Staff.salary.edit-subtitle'),
                'mode'      => 'view',
                'id_prefix' => 'salary-edit-',
                'locale'    => $locale,
                'urls'      => ['submit' => base_url('admin/staff/salary/edit')],
                'messages'  => ['saved' => lang('Staff.salary.updated'), 'saveFailed' => lang('Staff.form.failed')],
                'values'    => $salaries[0] + ['uid' => $uid],
                'fields'    => array_map(static function (array $f) use ($periods, $currency) {
                    if ('salary_period' === ($f['name'] ?? '')) {
                        $f['view'] = static fn ($v) => $periods[$v] ?? $v;
                    } elseif ('salary_amount' === ($f['name'] ?? '')) {
                        $f['view'] = static fn ($v) => formatMoney($v, $currency);
                    }
                    return $f;
                }, $salaryFields(true)),
            ];
        }
        $forms[] = [
            'form_id'   => 'staff-salary-add-form',
            'title'     => lang('Staff.salary.add-title'),
            'subtitle'  => lang([] === $salaries ? 'Staff.salary.add-first' : 'Staff.salary.add-subtitle'),
            'mode'      => 'create',
            'id_prefix' => 'salary-add-',
            'locale'    => $locale,
            'urls'      => ['submit' => base_url('admin/staff/salary/add')],
            'labels'    => ['save' => lang('Staff.salary.add-submit')],
            'messages'  => ['saved' => lang('Staff.salary.added'), 'saveFailed' => lang('Staff.form.failed')],
            'fields'    => [...$salaryFields(false), $history],
        ];

        return view('generic_form', [
            'locale'       => $locale,
            'slug'         => 'staff',
            'page_title'   => $name,
            'breadcrumbs'  => [base_url('admin/staff') => lang('System.page.staff')],
            'user'         => (array) session('user'),
            'formConfig'   => [
                'form_id'   => 'staff-stores-form',
                'title'     => lang('Staff.stores.title'),
                'subtitle'  => lang('Staff.stores.subtitle', [$name, $role]),
                'mode'      => 'edit',
                'id_prefix' => 'stores-',
                'locale'    => $locale,
                'urls'      => ['submit' => base_url('admin/staff/stores/save')],
                'messages'  => ['saved' => lang('Staff.stores.saved'), 'saveFailed' => lang('Staff.form.failed')],
                'values'    => ['stores' => $assignedStores, 'uid' => $uid],
                'fields'    => [
                    [] === $stores
                        ? ['type' => 'html', 'html' => '<p class="text-muted">' . esc(lang('Staff.stores.none')) . '</p>']
                        : ['name' => 'stores', 'type' => 'checkbox', 'label' => lang('Staff.stores.label'), 'options' => $stores, 'optional' => true,
                            'help' => lang('Staff.stores.help')],
                    $hidden,
                ],
            ],
            'extraForms'   => $forms,
            'customScript' => view('scripts/staff_form', ['field' => self::SERVICES_FIELD]),
        ]);
    }

    /**
     * Save the stores a person works in through LINK_INTERNAL_API v1/client/staff/stores/save { business_slug, uid, stores }
     */
    public function saveStores(): ResponseInterface
    {
        $post   = (array) $this->request->getPost();
        $uid    = trim((string) ($post['uid'] ?? ''));
        $stores = array_values(array_filter(array_map('strval', (array) ($post['stores'] ?? [])), static fn (string $s) => '' !== $s));
        [$code, $body] = $this->callApi('v1/client/staff/stores/save', ['business_slug' => (string) currentBusinessSlug(), 'uid' => $uid, 'stores' => $stores]);
        return $this->answer($code, $body, $uid, 'Staff.stores.saved');
    }

    /**
     * Save the services a person performs, their commission on each and their own commission threshold through
     * LINK_INTERNAL_API v1/client/staff/services/save { business_slug, uid, services, commission_threshold }: only
     * the ticked services are sent; an empty threshold means the business's
     */
    public function saveServices(): ResponseInterface
    {
        $post     = (array) $this->request->getPost();
        $uid      = trim((string) ($post['uid'] ?? ''));
        $services = [];
        foreach ((array) ($post[self::SERVICES_FIELD] ?? []) as $publicId => $service) {
            $service = (array) $service;
            if (empty($service['assigned'])) {
                continue;
            }
            $value      = trim((string) ($service['commission_value'] ?? ''));
            $services[] = [
                'public_id'        => (string) $publicId,
                'commission_value' => '' === $value ? '0' : $value,
                'commission_unit'  => (string) ($service['commission_unit'] ?? 'P'),
            ];
        }
        [$code, $body] = $this->callApi('v1/client/staff/services/save', [
            'business_slug'        => (string) currentBusinessSlug(),
            'uid'                  => $uid,
            'services'             => $services,
            'commission_threshold' => trim((string) ($post['commission_threshold'] ?? '')),
        ]);
        return $this->answer($code, $body, $uid, 'Staff.services.saved');
    }

    /**
     * Add a salary after the person's history through LINK_INTERNAL_API v1/client/staff/salary/add; the current
     * one, if open, is ended the day before
     */
    public function addSalary(): ResponseInterface
    {
        return $this->saveSalary('v1/client/staff/salary/add', 'Staff.salary.added');
    }

    /**
     * Correct the person's latest salary through LINK_INTERNAL_API v1/client/staff/salary/edit
     */
    public function editSalary(): ResponseInterface
    {
        return $this->saveSalary('v1/client/staff/salary/edit', 'Staff.salary.updated');
    }

    /**
     * How the session business pays commission (generic_form, view mode): on or off, the period sales are added up
     * over, and the threshold a person's sales must reach in it for any commission
     */
    public function commission(): string
    {
        [$code, $body] = $this->callApi('v1/client/commission-policy', ['business_slug' => (string) currentBusinessSlug()]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException((string) ($body['message'] ?? lang('Staff.commission.load-failed')));
        }
        $policy   = (array) $body['data']['commission'];
        $currency = (string) $body['data']['currency_code'];
        $statuses = lang('Staff.commission.status-options');
        $periods  = $this->periodOptions();
        $locale   = $this->request->getLocale();

        return view('generic_form', [
            'locale'     => $locale,
            'slug'       => 'commission',
            'page_title' => lang('System.page.commission'),
            'user'       => (array) session('user'),
            'formConfig' => [
                'form_id'  => 'commission-form',
                'title'    => lang('Staff.commission.title'),
                'subtitle' => lang('Staff.commission.subtitle', [(string) currentBusinessName()]),
                'mode'     => 'view',
                'locale'   => $locale,
                'urls'     => ['submit' => base_url('admin/business/commission/save'), 'edit' => base_url('admin/business/commission')],
                'messages' => ['saved' => lang('Staff.commission.saved'), 'saveFailed' => lang('Staff.commission.failed')],
                'values'   => $policy,
                'fields'   => [
                    ['name' => 'commission_status', 'type' => 'select', 'label' => lang('Staff.commission.status'), 'options' => $statuses, 'rules' => 'required',
                        'help' => lang('Staff.commission.status-help'), 'view' => static fn ($v) => $statuses[$v] ?? $v],
                    ['name' => 'commission_period', 'type' => 'select', 'label' => lang('Staff.commission.period'), 'options' => $periods, 'rules' => 'required',
                        'help' => lang('Staff.commission.period-help'), 'view' => static fn ($v) => $periods[$v] ?? $v],
                    ['name' => 'commission_threshold', 'type' => 'number', 'label' => lang('Staff.commission.threshold', [$currency]),
                        'rules' => 'required|numeric|greater_than_equal_to[0]', 'step' => '0.01', 'min' => 0,
                        'help' => lang('Staff.commission.threshold-help'), 'view' => static fn ($v) => formatMoney($v, $currency)],
                ],
            ],
        ]);
    }

    /**
     * Save how the session business pays commission through LINK_INTERNAL_API v1/client/commission-policy/save
     */
    public function commissionSave(): ResponseInterface
    {
        $policy = array_map(static fn ($v) => trim((string) $v), array_intersect_key((array) $this->request->getPost(), array_flip(self::POLICY_FIELDS)));
        [$code, $body] = $this->callApi('v1/client/commission-policy/save', ['business_slug' => (string) currentBusinessSlug(), 'commission' => $policy]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, lang('Staff.commission.failed'));
        }
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('Staff.commission.saved'),
            'redirect'  => base_url('admin/business/commission'),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * addSalary() and editSalary()
     */
    private function saveSalary(string $endpoint, string $saved): ResponseInterface
    {
        $post   = (array) $this->request->getPost();
        $uid    = trim((string) ($post['uid'] ?? ''));
        $salary = array_map(static fn ($v) => trim((string) $v), array_intersect_key($post, array_flip(self::SALARY_FIELDS)));
        $salary['end_date'] = ($salary['end_date'] ?? '') === '' ? null : $salary['end_date'];
        [$code, $body] = $this->callApi($endpoint, ['business_slug' => (string) currentBusinessSlug(), 'uid' => $uid, 'salary' => $salary]);
        return $this->answer($code, $body, $uid, $saved);
    }

    /**
     * The form's answer to a save on the person's page: back to it, so every form there shows what was saved
     */
    private function answer(int $code, array $body, string $uid, string $saved): ResponseInterface
    {
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, lang('Staff.form.failed'));
        }
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang($saved),
            'redirect'  => base_url('admin/staff/edit/' . rawurlencode($uid)),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * @return array<string, string> W, S, M => their names
     */
    private function periodOptions(): array
    {
        $out = [];
        foreach (self::PERIODS as $period) {
            $out[$period] = lang('Staff.period.' . $period);
        }
        return $out;
    }

    /**
     * Table Builder config of index(): each row's edit button opens the person; no Add button
     */
    private function listConfig(): array
    {
        $config = (new BusinessClientLinkModel())->getConfiguration();
        return [
            'table_id' => 'staff-table',
            'title'    => lang('Staff.list.title'),
            'subtitle' => lang('Staff.list.subtitle', [(string) currentBusinessName()]),
            'id_field' => 'uid',
            'urls'     => [
                'data' => base_url('admin/staff/data'),
                'edit' => base_url('admin/staff/edit/{uid}'),
            ],
            'messages' => ['loadFailed' => lang('Staff.list.load-failed')],
            'columns'  => [
                ['data' => 'full_name', 'title' => lang('Staff.list.column.full_name')],
                ['data' => 'client_role', 'title' => lang('Staff.list.column.client_role'), 'type' => 'badge', 'badges' => $config['client_role']['badges']],
                ['data' => 'client_status', 'title' => lang('Staff.list.column.client_status'), 'type' => 'badge', 'badges' => $config['client_status']['badges']],
                ['data' => 'store_count', 'title' => lang('Staff.list.column.store_count'), 'type' => 'number', 'class' => 'text-end', 'searchable' => false],
                ['data' => 'service_count', 'title' => lang('Staff.list.column.service_count'), 'type' => 'number', 'class' => 'text-end', 'searchable' => false],
                ['data' => 'salary', 'title' => lang('Staff.list.column.salary'), 'orderable' => false],
            ],
            'order'    => [['full_name', 'asc']],
        ];
    }
}
