<?php

use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\App;

/**
 * The session business's plan and subscription (admin/plans): its owners only, through v1/client/subscription/*
 * (stubbed here).
 *
 * @internal
 */
final class PlansPageTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const USER = ['id' => 1, 'uid' => 'test-uid', 'email_address' => 'client@example.com', 'full_name' => 'Test Client',
        'avatar_path' => null, 'display_name' => null, 'preferred_language' => 'en-US'];
    private const BUSINESS = ['business_slug' => 'otter-cafe', 'business_name' => 'Otter Cafe', 'country_code' => 'TH',
        'currency_code' => 'THB', 'plan_code' => 'free', 'subscription_status' => null];
    private const PLANS = ['plans' => [
        ['plan_code' => 'free', 'plan_description' => null, 'plan_tier' => 0, 'prices' => []],
        ['plan_code' => 'boost', 'plan_description' => 'For a growing cafe', 'plan_tier' => 1, 'prices' => [
            ['billing_cycle' => 'M', 'currency_code' => 'THB', 'price' => '299.00', 'next_price' => null, 'next_price_from' => null],
            ['billing_cycle' => 'Y', 'currency_code' => 'THB', 'price' => '2990.00', 'next_price' => null, 'next_price_from' => null],
        ]],
        ['plan_code' => 'velocity', 'plan_description' => null, 'plan_tier' => 2, 'prices' => [
            ['billing_cycle' => 'M', 'currency_code' => 'THB', 'price' => '599.00', 'next_price' => '699.00', 'next_price_from' => '2026-12-31 17:00:00'],
        ]],
        // not sold in this country: no prices, so not offered
        ['plan_code' => 'quantum', 'plan_description' => null, 'plan_tier' => 3, 'prices' => []],
    ]];
    private const SUBSCRIPTION = ['public_id' => 'sub000000001', 'business_slug' => 'otter-cafe', 'plan_code' => 'velocity',
        'billing_cycle' => 'M', 'currency_code' => 'THB', 'price' => '599.00', 'next_plan_code' => null, 'next_billing_cycle' => null,
        'subscription_status' => 'A', 'billing_mode' => 'M', 'billing_anchor_day' => 10, 'activated_at' => '2026-10-10 05:00:00',
        'current_period_start' => '2026-11-10 05:00:00', 'current_period_end' => '2026-12-10 05:00:00', 'next_billing_at' => '2026-12-10 05:00:00',
        'cancel_at_period_end' => 'N', 'cancel_requested_at' => null, 'suspended_at' => null, 'cancelled_at' => null, 'ended_at' => null,
        'created_at' => '2026-10-10 04:00:00', 'updated_at' => '2026-11-10 05:00:00'];
    private const PAID_INVOICE = ['public_id' => 'inv000000002', 'invoice_no' => 'INV-202611-000002', 'receipt_no' => 'RCT-202611-000002', 'plan_code' => 'velocity', 'billing_cycle' => 'M',
        'currency_code' => 'THB', 'list_price' => '599.00', 'discount_code' => null, 'discount_amount' => '0.00', 'amount_due' => '599.00',
        'invoice_status' => 'D', 'due_at' => '2026-11-13 05:00:00', 'paid_at' => '2026-11-08 02:00:00', 'voided_at' => null,
        'period_start' => '2026-11-10 05:00:00', 'period_end' => '2026-12-10 05:00:00', 'created_at' => '2026-11-03 05:00:00',
        'payments' => [['public_id' => 'pay000000002', 'method_code' => 'promptpay', 'currency_code' => 'THB', 'amount' => '599.00',
            'txn_status' => 'S', 'provider_ref' => null, 'failure_reason' => null, 'confirmed_at' => '2026-11-08 03:00:00', 'created_at' => '2026-11-08 02:00:00', 'has_slip' => true]]];
    private const PENDING_INVOICE = ['public_id' => 'inv000000001', 'invoice_no' => 'INV-202611-000001', 'receipt_no' => null, 'plan_code' => 'boost', 'billing_cycle' => 'M',
        'currency_code' => 'THB', 'list_price' => '299.00', 'discount_code' => 'HELLO10', 'discount_amount' => '29.90', 'amount_due' => '269.10',
        'invoice_status' => 'P', 'due_at' => '2026-11-11 05:00:00', 'paid_at' => null, 'voided_at' => null,
        'period_start' => null, 'period_end' => null, 'created_at' => '2026-11-10 05:00:00', 'payments' => []];
    private const METHODS = ['payment_methods' => [
        ['method_code' => 'promptpay', 'currency_code' => 'THB', 'confirmation_mode' => 'M', 'payee' => ['promptpay_id' => '0105555555555']],
        ['method_code' => 'bank_transfer', 'currency_code' => 'THB', 'confirmation_mode' => 'M',
            'payee' => ['bank_name' => 'Kasikorn', 'account_name' => 'OtterNova Co., Ltd.', 'account_number' => '123-4-56789-0']],
        ['method_code' => 'card', 'currency_code' => 'THB', 'confirmation_mode' => 'A', 'payee' => null],
    ]];

    /** @var list<array{string, array}> endpoints and payloads sent to the API */
    private array $calls = [];

    protected function tearDown(): void
    {
        Services::reset();
        parent::tearDown();
    }

    private function stubApi(array ...$replies): void
    {
        $curl = $this->createMock(CURLRequest::class);
        $curl->method('post')->willReturnCallback(function (string $url, array $options) use (&$replies) {
            $this->calls[] = [substr($url, strlen((string) getenv('LINK_INTERNAL_API'))), $options['json'] ?? $options['multipart'] ?? []];
            [$code, $body] = array_shift($replies) ?? [500, []];
            return (new Response(new App()))->setStatusCode($code)->setBody(json_encode($body));
        });
        Services::injectMock('curlrequest', $curl);
    }

    /**
     * Stub the API's GET of a document page with one reply
     */
    private function stubDocument(int $code, string $body, string $type): void
    {
        $curl = $this->createMock(CURLRequest::class);
        $curl->method('get')->willReturnCallback(function (string $url, array $options) use ($code, $body, $type) {
            $this->calls[] = [substr($url, strlen((string) getenv('LINK_INTERNAL_API'))), $options['headers']['Authorization'] ?? null];
            return (new Response(new App()))->setStatusCode($code)->setBody($body)->setContentType($type, '');
        });
        Services::injectMock('curlrequest', $curl);
    }

    private static function ok(array $data, ?string $message = null): array
    {
        return [200, ['status' => 'success', 'data' => $data] + (null === $message ? [] : ['message' => $message])];
    }

    private function session(string $role): array
    {
        return ['access_token' => 'test-token', 'expires_at' => time() + 3600, 'user' => self::USER,
            'business' => ['client_role' => $role, 'client_status' => 'A', 'is_default_business' => 'Y', 'business' => self::BUSINESS]];
    }

    private function json($result): array
    {
        return json_decode($result->getJSON(), true);
    }

    public function testOnlyOwnersManageThePlan(): void
    {
        foreach (['M', 'E'] as $role) {
            $this->withSession($this->session($role))->get('admin/plans')->assertRedirectTo(base_url('admin/dashboard'));
            $this->withSession($this->session($role))->post('admin/plans/cancel')->assertStatus(403);
        }
        $this->assertSame([], $this->calls);

        $this->withSession($this->session('M'))->get('admin/dashboard')->assertDontSee('admin/plans');
        $this->withSession($this->session('O'))->get('admin/dashboard')->assertSee(base_url('admin/plans'));
    }

    public function testFreePlanListsThePlansOfTheCountryToSubscribeTo(): void
    {
        $this->stubApi(self::ok(['plan_code' => 'free', 'subscription' => null, 'invoices' => []]), self::ok(self::PLANS),
            self::ok(['records_total' => 0, 'rows' => []]));

        $result = $this->withSession($this->session('O'))->get('admin/plans');

        $result->assertOK();
        $this->assertSame(['v1/client/subscription/current', 'v1/client/subscription/plans', 'v1/client/subscription/invoices'], array_column($this->calls, 0));
        $this->assertSame('otter-cafe', $this->calls[0][1]['business_slug']);
        $result->assertSee('id="current-plan-card"');
        $result->assertSee('Your business is on the free plan');
        $result->assertSee('data-mode="subscribe"');
        $result->assertSee('299.00บ.');
        $result->assertSee('2,990.00บ.');
        $result->assertSee('data-plan-code="boost" data-billing-cycle="Y"');
        $result->assertSee('data-plan-code="velocity" data-billing-cycle="M"');
        $result->assertSee('Not offered yearly');
        $result->assertSee('Changes to 699.00บ. from');
        $result->assertSee('id="checkout-modal"');
        // a plan with no price in this country is not offered
        $result->assertDontSee('data-plan-card="quantum"');
        $result->assertDontSee('id="payment-card"');
        $result->assertDontSee('id="billing-history-card"');
    }

    public function testActiveSubscriptionShowsItsDetailsAndTheChangesItCanMake(): void
    {
        $this->stubApi(self::ok(['plan_code' => 'velocity', 'subscription' => self::SUBSCRIPTION, 'invoices' => [self::PAID_INVOICE]]),
            self::ok(self::PLANS), self::ok(['records_total' => 1, 'rows' => [self::PAID_INVOICE]]));

        $result = $this->withSession($this->session('O'))->get('admin/plans');

        $result->assertOK();
        $result->assertSee('Velocity');
        $result->assertSee('599.00บ. / month');
        $result->assertSee('datetime="2026-10-10T05:00:00Z"'); // subscribed on
        $result->assertSee('Renews On');
        $result->assertSee('datetime="2026-12-10T05:00:00Z"');
        $result->assertSee('(PromptPay)');
        $result->assertSee('INV-202611-000002');
        $result->assertSee('data-plan-action="cancel"');
        $result->assertSee('Cancel at End of Period');
        $result->assertSee('data-mode="change"');
        // boost is a lower tier, so a downgrade; velocity monthly is the plan it is on
        $this->assertMatchesRegularExpression('#data-plan-code="boost" data-billing-cycle="M"[^>]*>\s*<i class="bi bi-arrow-down-circle"></i>\s*Downgrade#', $result->getBody());
        $result->assertSee('Current plan');
        $result->assertSee('Cancel your subscription to move to the free plan.');
        $result->assertSee('id="billing-history-card"');
        $result->assertSee(base_url('admin/plans/document/invoice/INV-202611-000002') . '" target="_blank"');
        $result->assertSee(base_url('admin/plans/document/receipt/RCT-202611-000002'));
        $result->assertDontSee('id="payment-card"');
        $result->assertDontSee('id="checkout-modal"');
    }

    public function testAScheduledChangeCanBeTakenBack(): void
    {
        $subscription = ['plan_code' => 'boost', 'price' => '299.00', 'next_plan_code' => 'velocity', 'next_billing_cycle' => 'M'] + self::SUBSCRIPTION;
        $this->stubApi(self::ok(['plan_code' => 'boost', 'subscription' => $subscription, 'invoices' => []]), self::ok(self::PLANS),
            self::ok(['records_total' => 0, 'rows' => []]));

        $result = $this->withSession($this->session('O'))->get('admin/plans');

        $result->assertSee('id="scheduled-change"');
        $result->assertSee('Velocity, billed every month,');
        $result->assertSee('Keep current plan');
        $this->assertMatchesRegularExpression('#data-plan-code="boost" data-billing-cycle="M"\s+data-confirm#', $result->getBody());
        // velocity monthly is scheduled, so it is marked rather than offered; boost yearly is a switch of cycle
        $result->assertSee('<span class="badge bg-info">From <time datetime="2026-12-10T05:00:00Z"');
        $this->assertDoesNotMatchRegularExpression('#data-plan-code="velocity" data-billing-cycle="M"#', $result->getBody());
        $this->assertMatchesRegularExpression('#data-plan-code="boost" data-billing-cycle="Y"[^>]*>\s*<i class="bi bi-arrow-left-right"></i>\s*Switch to yearly#', $result->getBody());
    }

    public function testACancellingSubscriptionOffersToResumeInsteadOfAChange(): void
    {
        $subscription = ['cancel_at_period_end' => 'Y', 'cancel_requested_at' => '2026-11-12 00:00:00'] + self::SUBSCRIPTION;
        $this->stubApi(self::ok(['plan_code' => 'velocity', 'subscription' => $subscription, 'invoices' => []]), self::ok(self::PLANS),
            self::ok(['records_total' => 0, 'rows' => []]));

        $result = $this->withSession($this->session('O'))->get('admin/plans');

        $result->assertSee('This subscription is cancelled and ends on');
        $result->assertSee('Expires On');
        $result->assertSee('data-plan-action="resume"');
        $result->assertSee('To change your plan, resume your subscription first.');
        $result->assertDontSee('id="plans-card"');
    }

    public function testAPendingSubscriptionShowsTheInvoiceAndTheWaysToPayIt(): void
    {
        $subscription = ['plan_code' => 'boost', 'price' => '299.00', 'subscription_status' => 'P', 'activated_at' => null,
            'current_period_start' => null, 'current_period_end' => null, 'next_billing_at' => null] + self::SUBSCRIPTION;
        $this->stubApi(self::ok(['plan_code' => 'free', 'subscription' => $subscription, 'invoices' => [self::PENDING_INVOICE]]),
            self::ok(self::PLANS), self::ok(self::METHODS), self::ok(['records_total' => 1, 'rows' => [self::PENDING_INVOICE]]));

        $result = $this->withSession($this->session('O'))->get('admin/plans');

        $result->assertOK();
        $this->assertSame(['v1/client/subscription/payment-methods', ['business_slug' => 'otter-cafe', 'invoice_public_id' => 'inv000000001']], $this->calls[2]);
        $result->assertSee('Awaiting Payment');
        $result->assertSee('This subscription starts once its first invoice is paid.');
        $result->assertSee('id="payment-card"');
        $result->assertSee(base_url('admin/plans/document/invoice/INV-202611-000001'));
        $result->assertDontSee('admin/plans/document/receipt/');
        $result->assertSee('269.10บ.');
        $result->assertSee('HELLO10');
        $result->assertSee('0105555555555');
        $result->assertSee('123-4-56789-0');
        $result->assertSee('name="slip"');
        // a method the provider confirms takes no slip
        $result->assertDontSee('value="card"');
        $result->assertSee('Cancel Subscription');
        $result->assertDontSee('id="plans-card"');
    }

    public function testAPaymentBeingCheckedHidesThePaymentForm(): void
    {
        $invoice      = ['payments' => [['public_id' => 'pay1', 'method_code' => 'promptpay', 'currency_code' => 'THB', 'amount' => '269.10',
            'txn_status' => 'P', 'provider_ref' => null, 'failure_reason' => null, 'confirmed_at' => null, 'created_at' => '2026-11-10 06:00:00', 'has_slip' => true]]] + self::PENDING_INVOICE;
        $subscription = ['subscription_status' => 'P'] + self::SUBSCRIPTION;
        $this->stubApi(self::ok(['plan_code' => 'free', 'subscription' => $subscription, 'invoices' => [$invoice]]), self::ok(self::PLANS),
            self::ok(['records_total' => 1, 'rows' => [$invoice]]));

        $result = $this->withSession($this->session('O'))->get('admin/plans');

        $result->assertSee('id="payment-under-review"');
        $result->assertDontSee('id="pay-form"');
        $this->assertNotContains('v1/client/subscription/payment-methods', array_column($this->calls, 0));
    }

    public function testSubscribeSendsTheSessionBusinessAndRefreshesItsPlan(): void
    {
        $switched = self::ok(['business' => ['client_role' => 'O', 'client_status' => 'A', 'is_default_business' => 'Y',
            'business' => ['subscription_status' => 'P'] + self::BUSINESS]]);
        $this->stubApi(self::ok(['invoice_public_id' => 'inv000000001'], 'Subscription created.'), $switched);

        $result = $this->withSession($this->session('O'))->withBodyFormat('json')
            ->post('admin/plans/subscribe', ['plan_code' => 'boost', 'billing_cycle' => 'Y', 'discount_code' => ' HELLO10 ', 'business_slug' => 'someone-else']);

        $result->assertOK();
        $this->assertSame(['v1/client/subscription/subscribe', ['business_slug' => 'otter-cafe', 'plan_code' => 'boost', 'billing_cycle' => 'Y', 'discount_code' => 'HELLO10']], $this->calls[0]);
        $this->assertSame('v1/client/business/switch', $this->calls[1][0]);
        $this->assertSame(['success' => true, 'message' => 'Subscription created.', 'redirect' => base_url('admin/plans')],
            array_intersect_key($this->json($result), ['success' => 1, 'message' => 1, 'redirect' => 1]));
        $this->assertSame('P', $_SESSION['business']['business']['subscription_status']);
    }

    public function testChangeGoesToChangePlanAndRefusalsComeBack(): void
    {
        $this->stubApi([409, ['status' => 'error', 'message' => 'This subscription ends at the end of its period. Resume it before changing its plan.']]);

        $result = $this->withSession($this->session('O'))->withBodyFormat('json')->post('admin/plans/change', ['plan_code' => 'boost', 'billing_cycle' => 'M']);

        $result->assertStatus(409);
        $this->assertSame(['v1/client/subscription/change-plan', ['business_slug' => 'otter-cafe', 'plan_code' => 'boost', 'billing_cycle' => 'M']], $this->calls[0]);
        $this->assertStringContainsString('Resume it before changing its plan', $this->json($result)['message']);
        $this->assertCount(1, $this->calls); // no session refresh after a refusal
    }

    public function testCancelAndResumeNameOnlyTheBusiness(): void
    {
        $this->stubApi(self::ok(['subscription' => self::SUBSCRIPTION]), [500, []], self::ok(['subscription' => self::SUBSCRIPTION]), [500, []]);

        $this->withSession($this->session('O'))->withBodyFormat('json')->post('admin/plans/cancel', ['plan_code' => 'x'])->assertOK();
        $this->withSession($this->session('O'))->withBodyFormat('json')->post('admin/plans/resume')->assertOK();

        $this->assertSame([['v1/client/subscription/cancel', ['business_slug' => 'otter-cafe']], ['v1/client/subscription/resume', ['business_slug' => 'otter-cafe']]],
            [$this->calls[0], $this->calls[2]]);
    }

    public function testPayingWithoutASlipIsRefusedHere(): void
    {
        $result = $this->withSession($this->session('O'))->post('admin/plans/pay', ['invoice_public_id' => 'inv000000001', 'method_code' => 'promptpay']);

        $result->assertStatus(422);
        $this->assertSame(['slip' => 'Please attach the payment slip.'], $this->json($result)['errors']);
        $this->assertSame([], $this->calls);
    }

    public function testADocumentOpensAsTheApiRendersIt(): void
    {
        $page = '<!DOCTYPE html><html lang="th"><body>ใบแจ้งหนี้ INV-202611-000001</body></html>';
        $this->stubDocument(200, $page, 'text/html; charset=UTF-8');

        $result = $this->withSession($this->session('O'))->get('admin/plans/document/invoice/inv-202611-000001');

        $result->assertOK();
        $this->assertSame([['v1/client/documents/invoice/INV-202611-000001', 'Bearer test-token']], $this->calls);
        $this->assertSame($page, $result->response()->getBody());
        $this->assertSame('text/html; charset=UTF-8', $result->response()->getHeaderLine('Content-Type'));
        $this->assertSame('inline; filename="INV-202611-000001.html"', $result->response()->getHeaderLine('Content-Disposition'));
        $this->assertSame('private, no-store', $result->response()->getHeaderLine('Cache-Control'));
    }

    public function testADocumentThatIsNotTheBusinesssIsNotFound(): void
    {
        $this->stubDocument(404, json_encode(['status' => 'error', 'message' => 'Receipt not found.']), 'application/json');
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $this->withSession($this->session('O'))->get('admin/plans/document/receipt/RCT-202611-000009');
    }

    public function testADocumentNumberIsCheckedBeforeAsking(): void
    {
        $this->stubDocument(200, '<html></html>', 'text/html');
        foreach (['invoice/RCT-202611-000001', 'receipt/INV-202611-000001', 'invoice/..%2F..%2Fstaff'] as $path) {
            try {
                $this->withSession($this->session('O'))->get('admin/plans/document/' . $path);
                $this->fail($path . ' was asked for');
            } catch (\CodeIgniter\Exceptions\PageNotFoundException) {
            }
        }
        $this->withSession($this->session('M'))->get('admin/plans/document/invoice/INV-202611-000001')->assertRedirectTo(base_url('admin/dashboard'));
        $this->assertSame([], $this->calls);
    }
}
