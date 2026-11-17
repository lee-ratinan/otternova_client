<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

/**
 * The calendar of the session business, for everyone working in it whatever their role: what is booked on a day
 * (group sessions and private appointments, each with the resources it holds and the people who work it), and one
 * session in full (its times, resources, participants and people). Read only, from LINK_INTERNAL_API
 * v1/client/calendar and v1/client/calendar/session. Times are of the store's own clock.
 */
class Calendar extends BaseController
{
    /**
     * The bookings of a day: ?date=Y-m-d, the business's today when left out (or not a date); ?mine=1 keeps those
     * the signed-in person works
     */
    public function index(): string|RedirectResponse
    {
        if (null === currentBusinessSlug()) {
            return redirect()->to(base_url('admin/dashboard'));
        }
        $date    = trim((string) $this->request->getGet('date'));
        $payload = ['business_slug' => (string) currentBusinessSlug()];
        if (self::isDate($date)) {
            $payload['date'] = $date;
        }
        [$code, $body] = $this->callApi('v1/client/calendar', $payload);
        $ok    = $code === 200 && ($body['status'] ?? '') === 'success';
        $data  = $ok ? (array) $body['data'] : [];
        $date    = (string) ($data['date'] ?? (self::isDate($date) ? $date : gmdate('Y-m-d')));
        $entries = array_values((array) ($data['entries'] ?? []));
        $mine    = '1' === (string) $this->request->getGet('mine');
        return view('calendar/index', [
            'locale'       => $this->request->getLocale(),
            'slug'         => 'calendar',
            'user'         => (array) session('user'),
            'date'         => $date,
            'mine'         => $mine,
            'mineCount'    => count(array_filter($entries, static fn (array $entry): bool => ! empty($entry['is_mine']))),
            'total'        => count($entries),
            'entries'      => $mine ? array_values(array_filter($entries, static fn (array $entry): bool => ! empty($entry['is_mine']))) : $entries,
            'error'        => $ok ? null : (string) ($body['message'] ?? lang('Calendar.load-failed')),
            'businessName' => (string) currentBusinessName(),
        ]);
    }

    /**
     * One session (or appointment) of the business: ?date= is the calendar day to go back to
     * @param string $publicId service_session.public_id
     */
    public function session(string $publicId): string|RedirectResponse
    {
        if (null === currentBusinessSlug()) {
            return redirect()->to(base_url('admin/dashboard'));
        }
        [$code, $body] = $this->callApi('v1/client/calendar/session', ['business_slug' => (string) currentBusinessSlug(), 'public_id' => $publicId]);
        if (404 === $code) {
            throw PageNotFoundException::forPageNotFound(lang('Calendar.session.not-found'));
        }
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException($body['message'] ?? lang('Calendar.load-failed'));
        }
        $session = (array) $body['data']['session'];
        $date    = trim((string) $this->request->getGet('date'));
        $back    = base_url('admin/calendar') . (self::isDate($date) ? '?date=' . $date : '');
        return view('calendar/session', [
            'locale'      => $this->request->getLocale(),
            'slug'        => 'calendar',
            'user'        => (array) session('user'),
            'page_title'  => esc((string) ($session['name'] ?? '')),
            'breadcrumbs' => [$back => lang('System.page.calendar')],
            'session'     => $session,
            'back'        => $back,
        ]);
    }

    private static function isDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return false !== $parsed && $parsed->format('Y-m-d') === $date;
    }
}
