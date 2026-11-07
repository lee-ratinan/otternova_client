<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use CURLFile;
use RuntimeException;

/**
 * The session business's subscription (admin/plans), for its owners only (BusinessManagerFilter with "owner"), through
 * LINK_INTERNAL_API v1/client/subscription/*. Each call names the business by the session's business_slug.
 *
 * - On a paid plan: the plan, when it started, the period it is paid up to and the last payment, with buttons to
 *   cancel at the end of the period (or take that back) and to move to another plan or billing cycle from the next
 *   period.
 * - On the free plan: the plans the business can have in its country, and the checkout: subscribe (with a discount
 *   code), then pay the first invoice by slip for the staff to check.
 * An invoice waiting for payment (the first one, a renewal, or a withholding tax top-up) is shown with the ways to pay it.
 *
 * Withholding tax: a business whose country has a withholding rule (Thailand: 3% on services) can ask to withhold it,
 * for the staff to approve. Its invoices are then less the tax (amount_payable), and for each one it pays it uploads
 * the certificate (50 ทวิ). One not sent by its due date blocks the next invoice, or is billed as a top-up.
 */
class Plans extends BaseController
{
    /** subscription_master.subscription_status */
    private const PENDING   = 'P';
    private const ACTIVE    = 'A';
    private const SUSPENDED = 'S';

    /** subscription_invoice.invoice_status and payment_transaction.txn_status */
    private const INVOICE_PENDING = 'P';
    private const INVOICE_PAID    = 'D';
    private const TXN_PENDING     = 'P';
    private const TXN_SUCCEEDED   = 'S';

    /** payment_method.confirmation_mode the API takes a slip for: confirmed by the staff */
    private const CONFIRMED_BY_STAFF = 'M';

    /** billing_cycle */
    private const CYCLES = ['M', 'Y'];

    /** subscription_invoice.invoice_type of a withholding tax top-up */
    private const TYPE_TOP_UP = 'W';

    /** subscription_invoice.wht_cert_status: waiting for the certificate, sent and being checked, billed as a top-up */
    private const CERT_WAITING   = 'W';
    private const CERT_UPLOADED  = 'U';
    private const CERT_TOPPED_UP = 'T';

    /** withholding_tax_certificate.cert_status */
    private const CERTIFICATE_REJECTED = 'R';

    /** How many invoices the billing history shows */
    private const HISTORY_LENGTH = 50;

    /** An invoice number (INV-YYYYMM-000123) or a receipt number (RCT-...), by the document it names */
    private const DOCUMENT_NUMBERS = ['invoice' => '/^INV-\d{6}-\d{6,}$/', 'receipt' => '/^RCT-\d{6}-\d{6,}$/'];

    public function index(): string
    {
        $slug = (string) currentBusinessSlug();
        [$code, $body] = $this->callApi('v1/client/subscription/current', ['business_slug' => $slug]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException($body['message'] ?? lang('BusinessPlans.load-failed'));
        }
        $current      = (array) ($body['data'] ?? []);
        $subscription = is_array($current['subscription'] ?? null) ? $current['subscription'] : null;

        [$code, $body] = $this->callApi('v1/client/subscription/plans', ['business_slug' => $slug]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException($body['message'] ?? lang('BusinessPlans.load-failed'));
        }
        $plans = array_map(fn (array $plan) => $this->planView($plan), (array) ($body['data']['plans'] ?? []));

        // the invoice waiting for payment, a top-up first as the other cannot be paid while it is late, and the ways to pay it
        $invoices = (array) ($current['invoices'] ?? []);
        $pending  = null;
        foreach ($invoices as $invoice) {
            if (self::INVOICE_PENDING === ($invoice['invoice_status'] ?? '')
                && (null === $pending || self::TYPE_TOP_UP === ($invoice['invoice_type'] ?? ''))) {
                $pending = $this->invoiceView($invoice);
            }
        }
        $methods = [];
        if (null !== $pending && ! $pending['under_review']) {
            [$code, $body] = $this->callApi('v1/client/subscription/payment-methods', ['business_slug' => $slug, 'invoice_public_id' => $pending['public_id']]);
            if ($code === 200 && ($body['status'] ?? '') === 'success') {
                $methods = array_values(array_filter((array) ($body['data']['payment_methods'] ?? []),
                    static fn ($method) => self::CONFIRMED_BY_STAFF === ($method['confirmation_mode'] ?? '')));
            }
        }

        // every invoice the business has had, newest first; left out when it cannot be loaded
        [$code, $body] = $this->callApi('v1/client/subscription/invoices', ['business_slug' => $slug, 'start' => 0, 'length' => self::HISTORY_LENGTH]);
        $history = $code === 200 && ($body['status'] ?? '') === 'success'
            ? array_map(fn (array $invoice) => $this->invoiceView($invoice), (array) ($body['data']['rows'] ?? []))
            : null;

        // the paid invoices whose withholding tax certificate is wanted or being checked, oldest first
        $certificatesDue = array_values(array_reverse(array_filter($history ?? array_map(fn (array $invoice) => $this->invoiceView($invoice), $invoices),
            static fn (array $invoice) => in_array($invoice['wht_cert_status'] ?? '', [self::CERT_WAITING, self::CERT_UPLOADED, self::CERT_TOPPED_UP], true))));
        $certificateLate = [] !== array_filter($certificatesDue, static fn (array $invoice) => $invoice['certificate_late']);

        $data = [
            'locale'       => $this->request->getLocale(),
            'slug'         => 'plans',
            'user'         => (array) session('user'),
            'businessName' => (string) currentBusinessName(),
            'planCode'     => (string) ($current['plan_code'] ?? ''),
            'subscription' => null === $subscription ? null : $this->subscriptionView($subscription, $plans, $invoices),
            'plans'        => $plans,
            'pending'      => $pending,
            'methods'      => $methods,
            'history'      => $history,
            'withholding'  => $this->withholdingView((array) ($current['withholding'] ?? [])),
            'certificates' => $certificatesDue,
            'certificateLate' => $certificateLate,
            'urls'         => [
                'discounts'      => base_url('admin/plans/discounts'),
                'subscribe'      => base_url('admin/plans/subscribe'),
                'apply-discount' => base_url('admin/plans/apply-discount'),
                'pay'            => base_url('admin/plans/pay'),
                'cancel'         => base_url('admin/plans/cancel'),
                'resume'         => base_url('admin/plans/resume'),
                'change'         => base_url('admin/plans/change'),
                'withholding'    => base_url('admin/plans/withholding'),
                'withdraw'       => base_url('admin/plans/withholding/withdraw'),
                'certificate'    => base_url('admin/plans/certificate'),
                'business'       => base_url('admin/business'),
            ],
        ];
        return view('admin_plans', $data);
    }

    /**
     * The listed discount codes the business can use for a plan and cycle: { plan_code, billing_cycle }
     */
    public function discounts(): ResponseInterface
    {
        [$code, $body] = $this->callApi('v1/client/subscription/discounts', $this->input(['plan_code', 'billing_cycle']));
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->failed($code, $body);
        }
        $discounts = [];
        foreach ((array) ($body['data']['discounts'] ?? []) as $discount) {
            $discounts[] = [
                'discount_code'        => (string) ($discount['discount_code'] ?? ''),
                'discount_description' => (string) ($discount['discount_description'] ?? ''),
            ];
        }
        return $this->response->setJSON(['success' => true, 'discounts' => $discounts, 'csrf_hash' => csrf_hash()]);
    }

    /**
     * Subscribe to a paid plan: { plan_code, billing_cycle, discount_code }. The page reloads with its first invoice
     * to pay (or active at once, when the discount leaves nothing to pay).
     */
    public function subscribe(): ResponseInterface
    {
        return $this->forward('v1/client/subscription/subscribe', $this->input(['plan_code', 'billing_cycle', 'discount_code']), 'BusinessPlans.subscribed');
    }

    /**
     * Apply a discount code to the invoice waiting for payment: { invoice_public_id, discount_code }
     */
    public function applyDiscount(): ResponseInterface
    {
        return $this->forward('v1/client/subscription/apply-discount', $this->input(['invoice_public_id', 'discount_code']), 'BusinessPlans.discount-applied');
    }

    /**
     * Send the payment of the invoice waiting for payment, multipart { invoice_public_id, method_code, provider_ref,
     * slip }, for the staff to check
     */
    public function pay(): ResponseInterface
    {
        $file = $this->request->getFile('slip');
        if (null === $file || ! $file->isValid()) {
            $tooLarge = null !== $file && in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            $message  = lang($tooLarge ? 'BusinessPlans.pay.slip-too-large' : 'BusinessPlans.pay.slip-required');
            return $this->response->setStatusCode($tooLarge ? 413 : 422)->setJSON([
                'success'   => false,
                'message'   => $message,
                'errors'    => ['slip' => $message],
                'csrf_hash' => csrf_hash(),
            ]);
        }
        $payload = [
            'business_slug'     => (string) currentBusinessSlug(),
            'invoice_public_id' => trim((string) $this->request->getPost('invoice_public_id')),
            'method_code'       => trim((string) $this->request->getPost('method_code')),
            'provider_ref'      => trim((string) $this->request->getPost('provider_ref')),
            'slip'              => new CURLFile($file->getTempName(), $file->getMimeType(), $file->getClientName()),
        ];
        return $this->forward('v1/client/subscription/pay', $payload, 'BusinessPlans.pay.sent', true);
    }

    /**
     * Ask to withhold tax from what the business pays: { legal_name, tax_branch_code, wht_rule_code }, for the staff
     * to approve
     */
    public function withholding(): ResponseInterface
    {
        return $this->forward('v1/client/subscription/withholding', $this->input(['legal_name', 'tax_branch_code', 'wht_rule_code']), 'BusinessPlans.withholding.requested');
    }

    /**
     * Stop withholding tax, or take back the request
     */
    public function withdrawWithholding(): ResponseInterface
    {
        return $this->forward('v1/client/subscription/withholding/withdraw', $this->input([]), 'BusinessPlans.withholding.withdrawn');
    }

    /**
     * Send the withholding tax certificate of a paid invoice, multipart { invoice_public_id, certificate_no, issued_on,
     * base_amount, wht_amount, certificate }, for the staff to check
     */
    public function certificate(): ResponseInterface
    {
        $file = $this->request->getFile('certificate');
        if (null === $file || ! $file->isValid()) {
            $tooLarge = null !== $file && in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            $message  = lang($tooLarge ? 'BusinessPlans.pay.slip-too-large' : 'BusinessPlans.certificates.file-required');
            return $this->response->setStatusCode($tooLarge ? 413 : 422)->setJSON([
                'success'   => false,
                'message'   => $message,
                'errors'    => ['certificate' => $message],
                'csrf_hash' => csrf_hash(),
            ]);
        }
        $payload = ['business_slug' => (string) currentBusinessSlug()];
        foreach (['invoice_public_id', 'certificate_no', 'issued_on', 'base_amount', 'wht_amount'] as $field) {
            $payload[$field] = trim((string) $this->request->getPost($field));
        }
        $payload['certificate'] = new CURLFile($file->getTempName(), $file->getMimeType(), $file->getClientName());
        return $this->forward('v1/client/subscription/certificate', $payload, 'BusinessPlans.certificates.sent', true);
    }

    /**
     * Cancel the subscription: an active one at the end of its period, a pending or suspended one now
     */
    public function cancel(): ResponseInterface
    {
        return $this->forward('v1/client/subscription/cancel', $this->input([]), 'BusinessPlans.cancelled');
    }

    /**
     * Take back a cancellation at the end of the period
     */
    public function resume(): ResponseInterface
    {
        return $this->forward('v1/client/subscription/resume', $this->input([]), 'BusinessPlans.resumed');
    }

    /**
     * Move to another plan or billing cycle from the next period: { plan_code, billing_cycle }. The plan and cycle
     * the subscription is on takes back a change set earlier.
     */
    public function change(): ResponseInterface
    {
        return $this->forward('v1/client/subscription/change-plan', $this->input(['plan_code', 'billing_cycle']), 'BusinessPlans.changed');
    }

    // ---- inside ----

    /**
     * The session business_slug and the named fields of the request (JSON or form), trimmed strings
     * @param list<string> $fields
     */
    private function input(array $fields): array
    {
        $json  = $this->request->is('json') ? (array) $this->request->getJSON(true) : [];
        $input = ['business_slug' => (string) currentBusinessSlug()];
        foreach ($fields as $field) {
            $value         = $json[$field] ?? $this->request->getPost($field);
            $input[$field] = is_scalar($value) ? trim((string) $value) : '';
        }
        return $input;
    }

    /**
     * Send a change to the API; on success the page reloads, and the session business follows its plan
     * @param string $fallback Language key of the success message when the API gives none
     */
    private function forward(string $endpoint, array $payload, string $fallback, bool $multipart = false): ResponseInterface
    {
        [$code, $body] = $this->callApi($endpoint, $payload, $multipart);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->failed($code, $body);
        }
        $this->refreshSessionBusiness();
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang($fallback),
            'data'      => $body['data'] ?? null,
            'redirect'  => base_url('admin/plans'),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * JSON answer of a failed API call; the API's field errors go through for the forms to highlight
     */
    private function failed(int $code, array $body): ResponseInterface
    {
        $errors = [];
        foreach ((array) ($body['errors'] ?? []) as $key => $message) {
            $errors[preg_replace('/^.*\./', '', (string) $key)] = $message;
        }
        return $this->response->setStatusCode($code === 200 ? 502 : $code)->setJSON([
            'success'   => false,
            'message'   => $body['message'] ?? lang('BusinessPlans.save-failed'),
            'errors'    => $errors ?: null,
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * The session business carries the plan it is on (plan_code, subscription_status); fetched afresh after a change,
     * and left as it is when it cannot be
     */
    private function refreshSessionBusiness(): void
    {
        $slug = currentBusinessSlug();
        if (null === $slug) {
            return;
        }
        [$code, $body] = $this->callApi('v1/client/business/switch', ['business_slug' => $slug]);
        $business = $body['data']['business'] ?? null;
        if ($code === 200 && ($body['status'] ?? '') === 'success' && is_array($business)) {
            session()->set('business', $business);
        }
    }

    /**
     * A plan as the page shows it: its name, and its prices by billing cycle, formatted
     */
    private function planView(array $plan): array
    {
        $code   = (string) ($plan['plan_code'] ?? '');
        $prices = [];
        foreach ((array) ($plan['prices'] ?? []) as $price) {
            $cycle = (string) ($price['billing_cycle'] ?? '');
            if (! in_array($cycle, self::CYCLES, true)) {
                continue;
            }
            $currency       = (string) ($price['currency_code'] ?? '');
            $prices[$cycle] = [
                'billing_cycle'   => $cycle,
                'currency_code'   => $currency,
                'price'           => (string) ($price['price'] ?? ''),
                'price_text'      => self::money($price['price'] ?? null, $currency),
                'next_price_text' => null === ($price['next_price'] ?? null) ? null : self::money($price['next_price'], $currency),
                'next_price_from' => $price['next_price_from'] ?? null,
            ];
        }
        return [
            'plan_code'        => $code,
            'name'             => self::planName($code),
            'plan_description' => (string) ($plan['plan_description'] ?? ''),
            'plan_tier'        => (int) ($plan['plan_tier'] ?? 0),
            'is_free'          => 0 === (int) ($plan['plan_tier'] ?? 0),
            'prices'           => $prices,
        ];
    }

    /**
     * The live subscription as the page shows it, with its plan's tier (to tell an upgrade from a downgrade) and its
     * last payment made
     * @param array $plans    planView() rows
     * @param array $invoices the subscription's invoices from the API, newest first
     */
    private function subscriptionView(array $subscription, array $plans, array $invoices): array
    {
        $byCode   = array_column($plans, null, 'plan_code');
        $planCode = (string) ($subscription['plan_code'] ?? '');
        $next     = $subscription['next_plan_code'] ?? null;

        $lastPaid = null;
        foreach ($invoices as $invoice) {
            if (self::INVOICE_PAID === ($invoice['invoice_status'] ?? '')) {
                $lastPaid = $this->invoiceView($invoice);
                break;
            }
        }
        $status = (string) ($subscription['subscription_status'] ?? '');
        return $subscription + [
            'name'            => self::planName($planCode),
            'plan_tier'       => (int) ($byCode[$planCode]['plan_tier'] ?? 0),
            'price_text'      => self::money($subscription['price'] ?? null, (string) ($subscription['currency_code'] ?? '')),
            'next_name'       => null === $next ? null : self::planName((string) $next),
            'is_active'       => self::ACTIVE === $status,
            'is_pending'      => self::PENDING === $status,
            'is_suspended'    => self::SUSPENDED === $status,
            'is_cancelling'   => 'Y' === ($subscription['cancel_at_period_end'] ?? 'N'),
            'last_payment'    => $lastPaid,
        ];
    }

    /**
     * The business's withholding as the page shows it; null when its country has no withholding rule
     */
    private function withholdingView(array $withholding): ?array
    {
        $rules = (array) ($withholding['rules'] ?? []);
        if ([] === $rules) {
            return null;
        }
        $byCode = array_column($rules, null, 'rule_code');
        $rule   = $byCode[$withholding['wht_rule_code'] ?? ''] ?? (1 === count($rules) ? reset($rules) : null);
        return $withholding + [
            'rules'        => $rules,
            'rule'         => $rule,
            'min_base_text' => null === $rule || null === ($rule['min_base_amount'] ?? null) ? null : self::money($rule['min_base_amount'], (string) $rule['currency_code']),
        ];
    }

    /**
     * GET admin/plans/document/{invoice|receipt}/{number}
     * The invoice or receipt as the API renders it (v1/client/documents/*), opened in a new tab, where it prints on A4
     * or saves as PDF; it reads the same every time. The API checks that the invoice is the session business's and
     * the client one of its owners.
     */
    public function document(string $kind, string $number): ResponseInterface
    {
        $number = strtoupper($number);
        if (! isset(self::DOCUMENT_NUMBERS[$kind]) || ! preg_match(self::DOCUMENT_NUMBERS[$kind], $number)) {
            throw PageNotFoundException::forPageNotFound(lang('BusinessPlans.document.not-found'));
        }
        [$code, $body, $type] = $this->fetchApiPage('v1/client/documents/' . $kind . '/' . $number);
        if (in_array($code, [403, 404], true)) {
            throw PageNotFoundException::forPageNotFound(lang('BusinessPlans.document.not-found'));
        }
        if (200 !== $code || ! str_starts_with(strtolower($type), 'text/html')) {
            log_message('error', 'LINK_INTERNAL_API v1/client/documents/' . $kind . ' returned HTTP ' . $code);
            throw new RuntimeException(lang('BusinessPlans.document.failed'));
        }

        // the page is the API's, so it keeps the API's policy: nothing but its own styles, fonts and embedded images
        return $this->response
            ->removeHeader('Cache-Control')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setHeader('Content-Disposition', 'inline; filename="' . $number . '.html"')
            ->setHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src data:")
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setContentType('text/html', 'UTF-8')
            ->setBody($body);
    }

    /**
     * An invoice as the page shows it: amounts formatted, whether a payment of it is being checked, the payment
     * that paid it, and its withholding tax and certificates
     */
    private function invoiceView(array $invoice): array
    {
        $currency    = (string) ($invoice['currency_code'] ?? '');
        $payments    = (array) ($invoice['payments'] ?? []);
        $underReview = false;
        $paidBy      = null;
        foreach ($payments as $payment) {
            $status       = (string) ($payment['txn_status'] ?? '');
            $underReview  = $underReview || self::TXN_PENDING === $status;
            if (self::TXN_SUCCEEDED === $status) {
                $paidBy = $payment;
            }
        }
        $discount     = (string) ($invoice['discount_amount'] ?? '0.00');
        $withheld     = (string) ($invoice['wht_amount'] ?? '0.00');
        $certificates = (array) ($invoice['certificates'] ?? []);
        $lastCert     = [] === $certificates ? null : end($certificates);
        $isTopUp      = self::TYPE_TOP_UP === ($invoice['invoice_type'] ?? '');
        $certDue      = $invoice['wht_cert_due_at'] ?? null;
        return $invoice + [
            'plan_name'            => $isTopUp ? lang('BusinessPlans.top-up.name', [$invoice['parent_invoice_no'] ?? '']) : self::planName((string) ($invoice['plan_code'] ?? '')),
            'is_top_up'            => $isTopUp,
            'list_price_text'      => self::money($invoice['list_price'] ?? null, $currency),
            'discount_amount_text' => self::isZero($discount) ? null : self::money($discount, $currency),
            'amount_due_text'      => self::money($invoice['amount_due'] ?? null, $currency),
            'wht_amount_text'      => self::isZero($withheld) ? null : self::money($withheld, $currency),
            'amount_payable_text'  => self::money($invoice['amount_payable'] ?? $invoice['amount_due'] ?? null, $currency),
            'under_review'         => $underReview,
            'paid_by'              => $paidBy,
            'last_payment'         => [] === $payments ? null : end($payments),
            'last_certificate'     => $lastCert,
            'certificate_rejected' => null !== $lastCert && self::CERTIFICATE_REJECTED === ($lastCert['cert_status'] ?? '')
                && self::CERT_UPLOADED !== ($invoice['wht_cert_status'] ?? ''),
            'certificate_late'     => self::CERT_WAITING === ($invoice['wht_cert_status'] ?? '') && null !== $certDue && $certDue <= gmdate('Y-m-d H:i:s'),
        ];
    }

    /**
     * The plan's name in the UI language; see planName()
     */
    private static function planName(string $code): string
    {
        return planName($code);
    }

    /**
     * An amount from the API ("299.00") in its currency; see formatMoney()
     */
    private static function money(mixed $amount, string $currency): string
    {
        return formatMoney($amount, $currency);
    }

    private static function isZero(string $amount): bool
    {
        return is_numeric($amount) && 0.0 === (float) $amount;
    }
}
