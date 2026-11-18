<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;

/**
 * The home page of a signed-in client (admin/dashboard): a shell with one card per widget the client's role in the
 * session business may see, each filled in on its own through admin/dashboard/widget/{name}, so a slow or failing
 * API call holds back that card only.
 *
 * - Everyone working in the business (owner O, manager M, employee E): today's bookings (an employee's own first),
 *   the orders that need something done, and the reviews waiting for approval.
 * - Owners and managers: the marketplace traffic of the last 7 days too.
 * - Owners: the revenue of today and of this month, and the subscription, too.
 *
 * The widgets read the same LINK_INTERNAL_API endpoints as their own pages, which check the role again on every call.
 */
class Dashboard extends BaseController
{
    /** Each widget, and the least role that sees it: E anyone working in the business, M owners and managers, O owners */
    public const WIDGETS = [
        'today'        => 'E',
        'orders'       => 'E',
        'reviews'      => 'E',
        'traffic'      => 'M',
        'revenue'      => 'O',
        'subscription' => 'O',
    ];

    /** How many rows a widget's list shows */
    private const LIST_LENGTH = 5;

    /** The days the traffic widget covers, today included */
    private const TRAFFIC_DAYS = 7;

    /**
     * GET admin/dashboard: the widgets the client may see, each a placeholder its script fills
     */
    public function index(): string
    {
        $role = self::role();
        return view('dashboard/index', [
            'locale'       => $this->request->getLocale(),
            'slug'         => 'dashboard',
            'user'         => (array) (session('user') ?? []),
            'role'         => $role,
            'widgets'      => null === $role ? [] : self::widgetsFor($role),
            'businessName' => (string) currentBusinessName(),
            'widgetUrl'    => base_url('admin/dashboard/widget/'),
            'birthday'     => $this->birthday(),
        ]);
    }

    /**
     * The client's birthday as 'm-d' (from client_profile.date_of_birth), for the page's script to wish them a happy
     * birthday when it is that day on the viewer's own clock (the server's is UTC); null when it is not known.
     * Sign-in does not send date_of_birth, so it is read from v1/client/account once and kept in the session user,
     * where saving the profile (Admin::profileEditPost()) keeps it up to date; a failed read is kept as unknown, so it
     * does not slow down every visit.
     */
    private function birthday(): ?string
    {
        $user = session('user');
        if (! is_array($user)) {
            return null;
        }
        if (! array_key_exists('date_of_birth', $user)) {
            [$code, $body] = $this->callApi('v1/client/account', []);
            $ok = $code === 200 && ($body['status'] ?? '') === 'success';
            $user['date_of_birth'] = $ok ? ($body['data']['profile']['date_of_birth'] ?? $body['data']['account']['date_of_birth'] ?? null) : null;
            session()->set('user', $user);
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr((string) $user['date_of_birth'], 0, 10));
        return false === $date ? null : $date->format('m-d');
    }

    /**
     * POST admin/dashboard/widget/{name}: { success, html, csrf_hash }, the widget's card body rendered; 403 when the
     * client's role may not see it, 404 for no such widget
     */
    public function widget(string $name): ResponseInterface
    {
        if (! isset(self::WIDGETS[$name])) {
            return $this->widgetResponse(404, null);
        }
        $role = self::role();
        if (null === $role || ! self::sees($role, self::WIDGETS[$name])) {
            return $this->widgetResponse(403, null);
        }
        $data = match ($name) {
            'today'        => $this->today($role),
            'orders'       => $this->orders($role),
            'reviews'      => $this->reviews(),
            'traffic'      => $this->traffic(),
            'revenue'      => $this->revenue(),
            'subscription' => $this->subscription(),
        };
        return $this->widgetResponse(200, view('dashboard/widget_' . $name, $data + ['locale' => $this->request->getLocale()]));
    }

    /**
     * The client's role in the session business: O, M or E; null with no business
     */
    public static function role(): ?string
    {
        if (null === currentBusinessSlug()) {
            return null;
        }
        return isBusinessOwner() ? 'O' : (canManageBusiness() ? 'M' : 'E');
    }

    /**
     * The widgets a role sees, in the order the page shows them
     * @return list<string>
     */
    public static function widgetsFor(string $role): array
    {
        return array_keys(array_filter(self::WIDGETS, static fn (string $least): bool => self::sees($role, $least)));
    }

    private static function sees(string $role, string $least): bool
    {
        $rank = ['E' => 1, 'M' => 2, 'O' => 3];
        return ($rank[$role] ?? 0) >= $rank[$least];
    }

    // --- widgets --------------------------------------------------------------------------------------------------

    /**
     * Today's bookings, from v1/client/calendar: an employee sees the ones they work; owners and managers all of them
     */
    private function today(string $role): array
    {
        [$ok, $data, $error] = $this->fetch('v1/client/calendar', [], 'Dashboard.load-failed');
        $entries = array_values((array) ($data['entries'] ?? []));
        $mine    = array_values(array_filter($entries, static fn (array $entry): bool => ! empty($entry['is_mine'])));
        $shown   = 'E' === $role ? $mine : $entries;
        $groups  = array_filter($entries, static fn (array $entry): bool => 'G' === ($entry['kind'] ?? ''));
        return [
            'error'    => $ok ? null : $error,
            'date'     => (string) ($data['date'] ?? gmdate('Y-m-d')),
            'onlyMine' => 'E' === $role,
            'total'    => count($entries),
            'mine'     => count($mine),
            'entries'  => array_slice($shown, 0, self::LIST_LENGTH + 3),
            'more'     => max(0, count($shown) - self::LIST_LENGTH - 3),
            'seats'    => array_sum(array_map(static fn (array $entry): int => (int) ($entry['seats_booked'] ?? 0), $groups)),
            'capacity' => array_sum(array_map(static fn (array $entry): int => (int) ($entry['capacity'] ?? 0), $groups)),
            'noShows'  => count(array_filter($entries, static fn (array $entry): bool => [] !== array_filter(
                (array) ($entry['customers'] ?? []),
                static fn (array $customer): bool => ! empty($customer['no_show'])
            ))),
        ];
    }

    /**
     * The orders that need something done, counted by v1/client/orders with a filter each, and the latest ones;
     * refunds due are counted for the owners and managers, who alone record them
     */
    private function orders(string $role): array
    {
        $counts = [
            'open'            => ['order_status' => 'open'],
            'awaiting_payment' => ['financial_status' => 'pending'],
            'to_fulfil'       => ['fulfillment_status' => 'open'],
        ];
        if ('E' !== $role) {
            $counts['refund_due'] = ['needs_refund' => true];
        }
        [$ok, $latest, $error] = $this->fetch('v1/client/orders', ['start' => 0, 'length' => self::LIST_LENGTH, 'search' => '', 'filters' => (object) []], 'Dashboard.load-failed');
        $tiles = [];
        if ($ok) {
            foreach ($counts as $key => $filters) {
                [$countOk, $count] = $this->fetch('v1/client/orders', ['start' => 0, 'length' => 1, 'search' => '', 'filters' => (object) $filters], 'Dashboard.load-failed');
                $tiles[$key] = [
                    'count' => $countOk ? (int) ($count['records_filtered'] ?? 0) : null,
                    'url'   => base_url('admin/sales'),
                ];
            }
        }
        return [
            'error'  => $ok ? null : $error,
            'tiles'  => $tiles,
            'orders' => array_values((array) ($latest['rows'] ?? [])),
            'total'  => (int) ($latest['records_total'] ?? 0),
        ];
    }

    /**
     * The reviews waiting for approval and the business's rating, from v1/client/artifact-reviews
     */
    private function reviews(): array
    {
        [$ok, $data, $error] = $this->fetch('v1/client/artifact-reviews', ['start' => 0, 'length' => 3, 'filters' => (object) ['review_status' => 'P']], 'Dashboard.load-failed');
        return [
            'error'   => $ok ? null : $error,
            'pending' => (int) ($data['records_filtered'] ?? 0),
            'reviews' => array_values((array) ($data['rows'] ?? [])),
            'rating'  => (array) ($data['business_rating'] ?? []),
        ];
    }

    /**
     * The marketplace traffic of the last TRAFFIC_DAYS days, from v1/client/traffic/overview
     */
    private function traffic(): array
    {
        $to   = new DateTimeImmutable('today');
        $from = $to->modify('-' . (self::TRAFFIC_DAYS - 1) . ' days');
        [$ok, $data, $error] = $this->fetch('v1/client/traffic/overview', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')], 'Dashboard.load-failed');
        // the headline numbers, wherever the overview keeps them
        $kpis = [];
        foreach ([$data['current'] ?? null, $data['kpis'] ?? null, $data['totals'] ?? null, $data] as $candidate) {
            if (is_array($candidate) && array_key_exists('visits', $candidate)) {
                $kpis = $candidate;
                break;
            }
        }
        return [
            'error' => $ok ? null : $error,
            'days'  => self::TRAFFIC_DAYS,
            'kpis'  => [
                'visits'          => isset($kpis['visits']) ? (int) $kpis['visits'] : null,
                'visitors'        => isset($kpis['visitors']) ? (int) $kpis['visitors'] : null,
                'orders'          => isset($kpis['orders']) ? (int) $kpis['orders'] : null,
                'conversion_rate' => isset($kpis['conversion_rate']) ? (float) $kpis['conversion_rate'] : null,
            ],
        ];
    }

    /**
     * Today's and this month's revenue, from v1/client/revenue, each currency apart; the month's sales by day
     * draw the sparkline
     */
    private function revenue(): array
    {
        [$ok, $month, $error] = $this->fetch('v1/client/revenue', ['period' => 'month'], 'Dashboard.load-failed');
        [, $day]              = $ok ? $this->fetch('v1/client/revenue', ['period' => 'day'], 'Dashboard.load-failed') : [false, []];
        $today = [];
        foreach ((array) ($day['currencies'] ?? []) as $sum) {
            $today[(string) ($sum['currency_code'] ?? '')] = $sum;
        }
        $currencies = [];
        foreach ((array) ($month['currencies'] ?? []) as $sum) {
            $code         = (string) ($sum['currency_code'] ?? '');
            $currencies[] = [
                'code'        => $code,
                'today'       => (string) ($today[$code]['sales'] ?? '0'),
                'todayOrders' => (int) ($today[$code]['orders'] ?? 0),
                'month'       => (string) ($sum['sales'] ?? '0'),
                'orders'      => (int) ($sum['orders'] ?? 0),
                'collected'   => (string) ($sum['collected'] ?? '0'),
                'outstanding' => (string) ($sum['outstanding'] ?? '0'),
                'refundDue'   => (string) ($sum['refund_due'] ?? '0'),
                'spark'       => array_map(static fn (array $p): float => (float) ($p['sales'] ?? 0), (array) ($sum['periods'] ?? [])),
            ];
        }
        return [
            'error'      => $ok ? null : $error,
            'currencies' => $currencies,
        ];
    }

    /**
     * The subscription and the invoice waiting for payment, from v1/client/subscription/current
     */
    private function subscription(): array
    {
        [$ok, $data, $error] = $this->fetch('v1/client/subscription/current', [], 'Dashboard.load-failed');
        $subscription = is_array($data['subscription'] ?? null) ? $data['subscription'] : null;
        $pending      = null;
        foreach ((array) ($data['invoices'] ?? []) as $invoice) {
            if ('P' === ($invoice['invoice_status'] ?? '')) {
                $pending = $invoice;
                break;
            }
        }
        return [
            'error'        => $ok ? null : $error,
            'subscription' => $subscription,
            'planName'     => null === $subscription ? null : planName((string) ($subscription['plan_code'] ?? '')),
            'pending'      => $pending,
        ];
    }

    // --- helpers --------------------------------------------------------------------------------------------------

    /**
     * An API call for the session business: [ok, data, error message]
     * @return array{bool, array, string}
     */
    private function fetch(string $endpoint, array $payload, string $failed): array
    {
        [$code, $body] = $this->callApi($endpoint, ['business_slug' => (string) currentBusinessSlug()] + $payload);
        $ok = $code === 200 && ($body['status'] ?? '') === 'success';
        return [$ok, $ok ? (array) ($body['data'] ?? []) : [], (string) ($body['message'] ?? lang($failed))];
    }

    private function widgetResponse(int $code, ?string $html): ResponseInterface
    {
        return $this->response->setStatusCode($code)->setJSON([
            'success'   => 200 === $code,
            'html'      => $html,
            'message'   => 200 === $code ? '' : lang('Dashboard.load-failed'),
            'csrf_hash' => csrf_hash(),
        ]);
    }
}
