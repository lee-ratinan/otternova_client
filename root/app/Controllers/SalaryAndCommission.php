<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use DateTimeImmutable;
use RuntimeException;

/**
 * What the people of the session business earn in a pay period, for its owners: everyone's salary and commission,
 * and one person's with how it is worked out. From LINK_INTERNAL_API v1/client/payroll and v1/client/payroll/person,
 * which work the pay period out (?cycle= W, S or M, and ?date= in it) on the business's own clock. Read only.
 */
class SalaryAndCommission extends BaseController
{
    private const CYCLES = ['M', 'S', 'W'];

    /** GET admin/salary-and-commission?cycle=M|S|W&date=Y-m-d: the business's this month when left out */
    public function index(): string|RedirectResponse
    {
        if (null === currentBusinessSlug() || ! isBusinessOwner()) {
            return redirect()->to(base_url('admin/dashboard'));
        }
        [$cycle, $payload] = $this->period();
        [$code, $body] = $this->callApi('v1/client/payroll', $payload);
        $ok   = $code === 200 && ($body['status'] ?? '') === 'success';
        $data = $ok ? (array) $body['data'] : [];

        return view('payroll/index', $this->common($cycle, $data, $payload) + [
            'people' => array_values((array) ($data['people'] ?? [])),
            'error'  => $ok ? null : (string) ($body['message'] ?? lang('SalaryAndCommission.load-failed')),
        ]);
    }

    /**
     * GET admin/salary-and-commission/{uid}?cycle=&date=: one person's pay in the period, worked out
     * @param string $uid auth_master.uid
     */
    public function person(string $uid): string|RedirectResponse
    {
        if (null === currentBusinessSlug() || ! isBusinessOwner()) {
            return redirect()->to(base_url('admin/dashboard'));
        }
        [$cycle, $payload] = $this->period();
        [$code, $body] = $this->callApi('v1/client/payroll/person', $payload + ['uid' => $uid]);
        if (404 === $code) {
            throw PageNotFoundException::forPageNotFound((string) ($body['message'] ?? lang('SalaryAndCommission.person.not-found')));
        }
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException((string) ($body['message'] ?? lang('SalaryAndCommission.load-failed')));
        }
        $data   = (array) $body['data'];
        $person = (array) $data['person'];
        $back   = self::link((string) $data['cycle'], (string) $data['from']);

        return view('payroll/person', $this->common($cycle, $data, $payload) + [
            'page_title'  => esc((string) ($person['name'] ?? '')),
            'breadcrumbs' => [$back => lang('System.page.salary-and-commission')],
            'person'      => $person,
            'back'        => $back,
        ]);
    }

    /** @return array{0: string, 1: array} the cycle asked for, and the API's payload for it */
    private function period(): array
    {
        $cycle   = (string) $this->request->getGet('cycle');
        $cycle   = in_array($cycle, self::CYCLES, true) ? $cycle : 'M';
        $date    = trim((string) $this->request->getGet('date'));
        $payload = ['business_slug' => (string) currentBusinessSlug(), 'cycle' => $cycle] + (self::isDate($date) ? ['date' => $date] : []);

        return [$cycle, $payload];
    }

    /** What both pages show of the period */
    private function common(string $cycle, array $data, array $payload): array
    {
        $from = (string) ($data['from'] ?? $payload['date'] ?? gmdate('Y-m-d'));
        $to   = (string) ($data['to'] ?? $from);

        return [
            'locale'       => $this->request->getLocale(),
            'slug'         => 'salary-and-commission',
            'user'         => (array) session('user'),
            'cycle'        => $cycle,
            'from'         => $from,
            'to'           => $to,
            'previous'     => self::link($cycle, (new DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d')),
            'next'         => self::link($cycle, (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d')),
            'currency'     => (string) ($data['currency_code'] ?? (session('business')['business']['currency_code'] ?? '')),
            'commission'   => (array) ($data['commission'] ?? []),
            'businessName' => (string) currentBusinessName(),
        ];
    }

    /** The list for the pay period of $cycle that holds $date */
    public static function link(string $cycle, string $date, string $uid = ''): string
    {
        return base_url('admin/salary-and-commission' . ('' === $uid ? '' : '/' . rawurlencode($uid))) . '?' . http_build_query(['cycle' => $cycle, 'date' => $date]);
    }

    private static function isDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return false !== $parsed && $parsed->format('Y-m-d') === $date;
    }
}
