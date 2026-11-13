<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * How the order numbers of the session business look: its prefix, the date part, when the running number starts
 * again, and how wide it is (IN2611-0001). Its owners and managers, as the API allows; through LINK_INTERNAL_API
 * v1/client/order-numbers and v1/client/order-numbers/save.
 */
class OrderNumbers extends BaseController
{
    private const FIELDS = ['order_prefix', 'order_date_part', 'order_reset', 'order_digits'];

    /**
     * The settings, the number the next order would get, and the form to change them, with a preview of the number
     */
    public function index(): string
    {
        [$code, $body] = $this->callApi('v1/client/order-numbers', ['business_slug' => (string) currentBusinessSlug()]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException((string) ($body['message'] ?? lang('OrderNumbers.load-failed')));
        }
        $settings = (array) $body['data']['order_numbers'];
        $next     = (string) $body['data']['next'];
        $locale   = $this->request->getLocale();
        $parts    = lang('OrderNumbers.date-part-options');
        $resets   = lang('OrderNumbers.reset-options');
        $digits   = array_combine(range(3, 6), array_map(static fn (int $n): string => lang('OrderNumbers.digits-option', [$n, str_pad('1', $n, '0', STR_PAD_LEFT)]), range(3, 6)));

        return view('generic_form', [
            'locale'       => $locale,
            'slug'         => 'order-numbers',
            'page_title'   => lang('System.page.order-numbers'),
            'user'         => (array) session('user'),
            'formConfig'   => [
                'form_id'  => 'order-numbers-form',
                'title'    => lang('OrderNumbers.title'),
                'subtitle' => lang('OrderNumbers.subtitle', [(string) currentBusinessName()]),
                'mode'     => 'view',
                'locale'   => $locale,
                'urls'     => ['submit' => base_url('admin/business/order-numbers/save'), 'edit' => base_url('admin/business/order-numbers')],
                'messages' => ['saved' => lang('OrderNumbers.saved'), 'saveFailed' => lang('OrderNumbers.failed')],
                'values'   => $settings,
                'fields'   => [
                    ['name' => 'order_prefix', 'type' => 'text', 'label' => lang('OrderNumbers.prefix'), 'optional' => true,
                        'maxlength' => 4, 'pattern' => '[A-Za-z]{0,4}', 'autocomplete' => 'off', 'attrs' => ['class' => 'text-uppercase'],
                        'rules' => 'permit_empty|alpha|max_length[4]', 'help' => lang('OrderNumbers.prefix-help'),
                        'view' => static fn ($v) => $v === '' || $v === null ? lang('OrderNumbers.none') : $v],
                    ['name' => 'order_date_part', 'type' => 'select', 'label' => lang('OrderNumbers.date-part'), 'options' => $parts, 'rules' => 'required',
                        'help' => lang('OrderNumbers.date-part-help'), 'view' => static fn ($v) => $parts[$v] ?? $v],
                    ['name' => 'order_reset', 'type' => 'select', 'label' => lang('OrderNumbers.reset'), 'options' => $resets, 'rules' => 'required',
                        'help' => lang('OrderNumbers.reset-help'), 'view' => static fn ($v) => $resets[$v] ?? $v],
                    ['name' => 'order_digits', 'type' => 'select', 'label' => lang('OrderNumbers.digits'), 'options' => $digits, 'rules' => 'required',
                        'help' => lang('OrderNumbers.digits-help'), 'view' => static fn ($v) => $digits[(int) $v] ?? $v],
                    ['type' => 'html', 'html' => view('partials/order_number_preview', ['next' => $next])],
                ],
            ],
            'customScript' => view('scripts/order_numbers', ['next' => $next]),
        ]);
    }

    /**
     * Save the settings through v1/client/order-numbers/save; the orders numbered already keep their numbers
     */
    public function save(): ResponseInterface
    {
        $settings = array_map(static fn ($v) => trim((string) $v), array_intersect_key((array) $this->request->getPost(), array_flip(self::FIELDS)));
        if (isset($settings['order_prefix'])) {
            $settings['order_prefix'] = strtoupper($settings['order_prefix']);
        }
        if (isset($settings['order_digits'])) {
            $settings['order_digits'] = (int) $settings['order_digits'];
        }
        [$code, $body] = $this->callApi('v1/client/order-numbers/save', ['business_slug' => (string) currentBusinessSlug(), 'order_numbers' => $settings]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, lang('OrderNumbers.failed'));
        }

        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('OrderNumbers.saved'),
            'redirect'  => base_url('admin/business/order-numbers'),
            'csrf_hash' => csrf_hash(),
        ]);
    }
}
