<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use DateTimeImmutable;

/**
 * The revenue of the session business, for its owners: what the orders placed in a period come to, from
 * LINK_INTERNAL_API v1/client/revenue. The period is a day, a week (Monday to Sunday) or a month around ?date=, or
 * ?from= to ?to= (period=custom); the API works the days out on the business's own clock. Read only.
 */
class RevenueReport extends BaseController
{
    /** The periods kept around a date; custom is from and to */
    private const PERIODS = ['day', 'week', 'month', 'custom'];

    /**
     * GET admin/revenue-report?period=day|week|month&date=Y-m-d, or ?period=custom&from=Y-m-d&to=Y-m-d; today when
     * left out
     */
    public function index(): string|RedirectResponse
    {
        if (null === currentBusinessSlug() || ! isBusinessOwner()) {
            return redirect()->to(base_url('admin/dashboard'));
        }
        $get    = fn (string $name): string => trim((string) $this->request->getGet($name));
        $period = in_array($get('period'), self::PERIODS, true) ? $get('period') : 'day';
        $from   = $get('from');
        $to     = $get('to');
        if ('custom' === $period && self::isDate($from)) {
            $payload = ['from' => $from, 'to' => self::isDate($to) ? $to : $from];
        } else {
            $period  = 'custom' === $period ? 'day' : $period;
            $payload = ['period' => $period] + (self::isDate($get('date')) ? ['date' => $get('date')] : []);
        }
        [$code, $body] = $this->callApi('v1/client/revenue', ['business_slug' => (string) currentBusinessSlug()] + $payload);
        $ok   = $code === 200 && ($body['status'] ?? '') === 'success';
        $data = $ok ? (array) $body['data'] : [];
        $from = (string) ($data['from'] ?? $payload['from'] ?? $payload['date'] ?? gmdate('Y-m-d'));
        $to   = (string) ($data['to'] ?? $payload['to'] ?? $from);

        return view('revenue/index', [
            'locale'       => $this->request->getLocale(),
            'slug'         => 'revenue-report',
            'user'         => (array) session('user'),
            'period'       => $period,
            'from'         => $from,
            'to'           => $to,
            'previous'     => $this->shifted($period, $from, $to, -1),
            'next'         => $this->shifted($period, $from, $to, 1),
            'granularity'  => (string) ($data['granularity'] ?? 'day'),
            'currencies'   => array_values((array) ($data['currencies'] ?? [])),
            'orders'       => array_values((array) ($data['orders'] ?? [])),
            'orderCount'   => (int) ($data['order_count'] ?? 0),
            'error'        => $ok ? null : (string) ($body['message'] ?? lang('RevenueReport.load-failed')),
            'businessName' => (string) currentBusinessName(),
        ]);
    }

    /**
     * The link to the period before ($step -1) or after (1) the one shown: the day, week or month next to it, or a
     * custom range as long as it
     */
    private function shifted(string $period, string $from, string $to, int $step): string
    {
        $start = new DateTimeImmutable($from);
        $query = match ($period) {
            'day'   => ['period' => 'day', 'date' => $start->modify($step . ' day')->format('Y-m-d')],
            'week'  => ['period' => 'week', 'date' => $start->modify(($step * 7) . ' days')->format('Y-m-d')],
            'month' => ['period' => 'month', 'date' => $start->modify('first day of ' . ($step > 0 ? 'next' : 'previous') . ' month')->format('Y-m-d')],
            default => (function () use ($start, $to, $step): array {
                $days = (int) $start->diff(new DateTimeImmutable($to))->days + 1;
                return ['period' => 'custom', 'from' => $start->modify(($step * $days) . ' days')->format('Y-m-d'),
                    'to' => (new DateTimeImmutable($to))->modify(($step * $days) . ' days')->format('Y-m-d')];
            })(),
        };

        return base_url('admin/revenue-report') . '?' . http_build_query($query);
    }

    private static function isDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return false !== $parsed && $parsed->format('Y-m-d') === $date;
    }
}
