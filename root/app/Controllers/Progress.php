<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * The setup guide of a new business (admin/progress): the steps an owner takes to get the session business ready to
 * sell, in the order they build on each other, each with a link to its page and how far it has come. The page is a
 * shell, like the dashboard: each step is checked on its own through admin/progress/check/{step}, which reads the same
 * LINK_INTERNAL_API endpoint as the step's own page, so a slow or failing call holds back that step only.
 *
 * A step is required (the progress bar counts it) or optional (shown, never held against the business). Some steps
 * are for a plan only: the upgrade for the free plan, the website, web pages and employees for a paid one.
 *
 * Owners only (the routes' manager:owner filter), as several of the steps (plans, employees) are theirs alone.
 */
class Progress extends BaseController
{
    /** The phases of the guide, in order, and their steps */
    public const PHASES = [
        'business' => ['business', 'plans', 'stores', 'website', 'webpages'],
        'team'     => ['employees', 'staff-attributes', 'commission', 'staff'],
        'selling'  => ['payment-methods', 'shipping-methods', 'resource-types', 'resources', 'order-numbers'],
        'catalog'  => ['artifact-attributes', 'artifact-categories', 'artifacts', 'sessions'],
        'extras'   => ['social-media', 'tags', 'discounts'],
    ];

    /**
     * Each step: [its page, icon, required, the plans it is for: 'free', 'paid' or null for every plan]
     */
    public const STEPS = [
        'business'            => ['admin/business', 'bi-shop', true, null],
        'plans'               => ['admin/plans', 'bi-gem', false, 'free'],
        'stores'              => ['admin/business/stores', 'bi-geo-alt', true, null],
        'website'             => ['admin/business/website', 'bi-palette', true, 'paid'],
        'webpages'            => ['admin/business/webpages', 'bi-file-earmark-richtext', true, 'paid'],
        'employees'           => ['admin/employees', 'bi-person-plus', true, 'paid'],
        'staff-attributes'    => ['admin/staff/attributes', 'bi-card-checklist', false, null],
        'commission'          => ['admin/business/commission', 'bi-percent', false, null],
        'staff'               => ['admin/staff', 'bi-person-badge', true, null],
        'payment-methods'     => ['admin/business/payment-methods', 'bi-credit-card', true, null],
        'shipping-methods'    => ['admin/business/shipping-methods', 'bi-truck', false, null],
        'resource-types'      => ['admin/business/resource-types', 'bi-diagram-3', false, null],
        'resources'           => ['admin/business/resources', 'bi-door-open', false, null],
        'order-numbers'       => ['admin/business/order-numbers', 'bi-123', true, null],
        'artifact-attributes' => ['admin/artifacts/attributes', 'bi-sliders', false, null],
        'artifact-categories' => ['admin/artifacts/categories', 'bi-tags', true, null],
        'artifacts'           => ['admin/artifacts', 'bi-box', true, null],
        'sessions'            => ['admin/sessions', 'bi-people', false, null],
        'social-media'        => ['admin/business/social-media', 'bi-share', false, null],
        'tags'                => ['admin/business/tags', 'bi-hash', false, null],
        'discounts'           => ['admin/business/discounts', 'bi-ticket-perforated', false, null],
    ];

    /** The business details the guide asks for beyond what creating the business already needs */
    public const BUSINESS_DETAILS = ['business_description', 'business_mood_and_tone', 'contact_person_name',
        'contact_email_address', 'contact_phone_number', 'contact_address_line_1', 'contact_city', 'contact_postal_code'];

    /** A step's states: done, still to do, not done but optional, or not known as its page could not be read */
    public const DONE     = 'done';
    public const TODO     = 'todo';
    public const OPTIONAL = 'optional';

    /** plan_code of the free plan */
    private const FREE_PLAN = 'free';

    /**
     * GET admin/progress: the steps of the business's plan, by phase, each a placeholder its script fills
     */
    public function index(): string
    {
        $paid  = $this->isPaid();
        $steps = self::stepsFor($paid);
        $phases = [];
        foreach (self::PHASES as $phase => $names) {
            $shown = array_values(array_intersect($names, $steps));
            if ([] !== $shown) {
                $phases[$phase] = $shown;
            }
        }
        return view('progress/index', [
            'locale'       => $this->request->getLocale(),
            'slug'         => 'progress',
            'user'         => (array) (session('user') ?? []),
            'businessName' => (string) currentBusinessName(),
            'paid'         => $paid,
            'phases'       => $phases,
            'checkUrl'     => base_url('admin/progress/check/'),
        ]);
    }

    /**
     * POST admin/progress/check/{step}: { success, state, note, csrf_hash }, how far the step has come; 404 for a step
     * that is not in the guide
     */
    public function check(string $step): ResponseInterface
    {
        if (! isset(self::STEPS[$step])) {
            return $this->checkResponse(404, null, '');
        }
        [$ok, $state, $note] = match ($step) {
            'business'            => $this->business(),
            'plans'               => [true, self::OPTIONAL, lang('Progress.note.plans')],
            'stores'              => $this->counted('v1/client/stores', 'stores', 'stores'),
            'website'             => $this->website(),
            'webpages'            => $this->counted('v1/client/webpages', 'pages', 'webpages'),
            'employees'           => $this->employees(),
            'staff-attributes'    => $this->counted('v1/client/staff-attributes', 'attributes', 'staff-attributes'),
            'commission'          => $this->commission(),
            'staff'               => $this->staff(),
            'payment-methods'     => $this->counted('v1/client/payment-methods', 'payment_methods', 'payment-methods'),
            'shipping-methods'    => $this->counted('v1/client/shipping-methods', 'shipping_methods', 'shipping-methods'),
            'resource-types'      => $this->counted('v1/client/resource-types', 'resource_types', 'resource-types'),
            'resources'           => $this->counted('v1/client/resources', 'resources', 'resources'),
            'order-numbers'       => $this->orderNumbers(),
            'artifact-attributes' => $this->counted('v1/client/artifact-attributes', 'attributes', 'artifact-attributes'),
            'artifact-categories' => $this->counted('v1/client/artifact-categories', 'categories', 'artifact-categories'),
            'artifacts'           => $this->artifacts(),
            'sessions'            => $this->counted('v1/client/sessions', 'sessions', 'sessions', ['period' => 'upcoming']),
            'social-media'        => $this->counted('v1/client/social-links', 'links', 'social-media'),
            'tags'                => $this->counted('v1/client/tags', 'tags', 'tags'),
            'discounts'           => $this->counted('v1/client/discounts', 'discounts', 'discounts'),
        };
        return $ok ? $this->checkResponse(200, $state, $note) : $this->checkResponse(502, null, $note);
    }

    /**
     * The steps of a free or paid business, in the guide's order
     * @return list<string>
     */
    public static function stepsFor(bool $paid): array
    {
        $order = array_merge(...array_values(self::PHASES));
        return array_values(array_filter($order, static function (string $step) use ($paid): bool {
            $for = self::STEPS[$step][3];
            return null === $for || ($paid ? 'paid' : 'free') === $for;
        }));
    }

    /**
     * Whether the session business is on a paid plan: the plan_code of v1/client/subscription/current is the free one
     * unless its subscription is active. When it cannot be read, the session business's own plan_code decides.
     */
    private function isPaid(): bool
    {
        [$ok, $data] = $this->fetch('v1/client/subscription/current', []);
        $plan = $ok ? ($data['plan_code'] ?? null) : null;
        if (null === $plan) {
            $business = session('business');
            $plan     = is_array($business) ? ($business['business']['plan_code'] ?? $business['plan_code'] ?? null) : null;
        }
        return '' !== (string) $plan && self::FREE_PLAN !== (string) $plan;
    }

    // --- the checks: [ok, state, note] ----------------------------------------------------------------------------

    /**
     * A step done once its list has a row: [ok, state, "{n} …" or the empty note]
     */
    private function counted(string $endpoint, string $key, string $step, array $payload = []): array
    {
        [$ok, $data, $error] = $this->fetch($endpoint, $payload);
        if (! $ok) {
            return [false, null, $error];
        }
        $count = count((array) ($data[$key] ?? []));
        if ($count > 0) {
            return [true, self::DONE, lang('Progress.count.' . $step, [$count])];
        }
        return [true, self::STEPS[$step][2] ? self::TODO : self::OPTIONAL, lang('Progress.empty.' . $step)];
    }

    /**
     * The business details beyond its name, filled in or not (BUSINESS_DETAILS), from v1/client/business/switch
     */
    private function business(): array
    {
        [$ok, $data, $error] = $this->fetch('v1/client/business/switch', []);
        $business = is_array($data['business']['business'] ?? null) ? $data['business']['business'] : (array) ($data['business'] ?? []);
        if (! $ok || [] === $business) {
            return [false, null, $error];
        }
        $filled = count(array_filter(self::BUSINESS_DETAILS, static fn (string $name): bool => '' !== trim((string) ($business[$name] ?? ''))));
        $total  = count(self::BUSINESS_DETAILS);
        return [true, $filled === $total ? self::DONE : self::TODO, lang('Progress.note.business', [$filled, $total])];
    }

    /**
     * The website is set up once it is in a language, from v1/client/website
     */
    private function website(): array
    {
        [$ok, $data, $error] = $this->fetch('v1/client/website', []);
        if (! $ok) {
            return [false, null, $error];
        }
        $count = count((array) ($data['languages'] ?? []));
        return $count > 0
            ? [true, self::DONE, lang('Progress.count.website', [$count])]
            : [true, self::TODO, lang('Progress.empty.website')];
    }

    /**
     * Someone other than the owner works in the business, from v1/client/employees; terminated people do not count
     */
    private function employees(): array
    {
        [$ok, $data, $error] = $this->fetch('v1/client/employees', []);
        if (! $ok) {
            return [false, null, $error];
        }
        $me     = (string) (session('user')['uid'] ?? '');
        $others = array_filter((array) ($data['employees'] ?? []), static fn (array $employee): bool => (string) ($employee['uid'] ?? '') !== $me
            && 'T' !== ($employee['client_status'] ?? ''));
        return [] === $others
            ? [true, self::TODO, lang('Progress.empty.employees')]
            : [true, self::DONE, lang('Progress.count.employees', [count($others)])];
    }

    /**
     * Commission is optional: done when it is paid, otherwise off (fine for a business that pays none), from
     * v1/client/commission-policy
     */
    private function commission(): array
    {
        [$ok, $data, $error] = $this->fetch('v1/client/commission-policy', []);
        if (! $ok) {
            return [false, null, $error];
        }
        return 'A' === ($data['commission']['commission_status'] ?? null)
            ? [true, self::DONE, lang('Progress.note.commission-on')]
            : [true, self::OPTIONAL, lang('Progress.note.commission-off')];
    }

    /**
     * Every active person works at a store and has a salary, from v1/client/staff; the people with no services are
     * told, as some (a cashier) have none
     */
    private function staff(): array
    {
        [$ok, $data, $error] = $this->fetch('v1/client/staff', []);
        if (! $ok) {
            return [false, null, $error];
        }
        $people = array_filter((array) ($data['staff'] ?? []), static fn (array $person): bool => 'T' !== ($person['client_status'] ?? ''));
        if ([] === $people) {
            return [true, self::TODO, lang('Progress.empty.staff')];
        }
        $noStore    = count(array_filter($people, static fn (array $person): bool => (int) ($person['store_count'] ?? 0) < 1));
        $noSalary   = count(array_filter($people, static fn (array $person): bool => null === ($person['salary_amount'] ?? null)));
        $noServices = count(array_filter($people, static fn (array $person): bool => (int) ($person['service_count'] ?? 0) < 1));
        $missing    = [];
        if ($noStore > 0) {
            $missing[] = lang('Progress.staff.no-store', [$noStore]);
        }
        if ($noSalary > 0) {
            $missing[] = lang('Progress.staff.no-salary', [$noSalary]);
        }
        if ($noServices > 0) {
            $missing[] = lang('Progress.staff.no-services', [$noServices]);
        }
        $note = [] === $missing ? lang('Progress.count.staff', [count($people)]) : implode(' · ', $missing);
        return [true, 0 === $noStore && 0 === $noSalary ? self::DONE : self::TODO, $note];
    }

    /**
     * The order numbers have a prefix, and the next one is shown, from v1/client/order-numbers
     */
    private function orderNumbers(): array
    {
        [$ok, $data, $error] = $this->fetch('v1/client/order-numbers', []);
        if (! $ok) {
            return [false, null, $error];
        }
        $next = (string) ($data['next'] ?? '');
        return '' !== trim((string) ($data['order_numbers']['order_prefix'] ?? ''))
            ? [true, self::DONE, lang('Progress.note.order-numbers', [$next])]
            : [true, self::TODO, lang('Progress.empty.order-numbers')];
    }

    /**
     * The products and services, counted apart, from v1/client/artifacts
     */
    private function artifacts(): array
    {
        [$ok, $data, $error] = $this->fetch('v1/client/artifacts', ['filters' => (object) []]);
        if (! $ok) {
            return [false, null, $error];
        }
        $artifacts = (array) ($data['artifacts'] ?? []);
        if ([] === $artifacts) {
            return [true, self::TODO, lang('Progress.empty.artifacts')];
        }
        $services = count(array_filter($artifacts, static fn (array $artifact): bool => 'S' === ($artifact['artifact_type'] ?? '')));
        return [true, self::DONE, lang('Progress.count.artifacts', [count($artifacts) - $services, $services])];
    }

    // --- helpers --------------------------------------------------------------------------------------------------

    /**
     * An API call for the session business: [ok, data, error message]
     * @return array{bool, array, string}
     */
    private function fetch(string $endpoint, array $payload): array
    {
        [$code, $body] = $this->callApi($endpoint, ['business_slug' => (string) currentBusinessSlug()] + $payload);
        $ok = $code === 200 && ($body['status'] ?? '') === 'success';
        return [$ok, $ok ? (array) ($body['data'] ?? []) : [], (string) ($body['message'] ?? lang('Progress.load-failed'))];
    }

    private function checkResponse(int $code, ?string $state, string $note): ResponseInterface
    {
        return $this->response->setStatusCode($code)->setJSON([
            'success'   => 200 === $code,
            'state'     => $state,
            'note'      => $note,
            'message'   => 200 === $code ? '' : ('' === $note ? lang('Progress.load-failed') : $note),
            'csrf_hash' => csrf_hash(),
        ]);
    }
}
