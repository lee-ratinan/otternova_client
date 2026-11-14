<?php

use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\App;

/**
 * The orders of the session business (admin/sales/*): anyone working in it, through v1/client/orders, order,
 * order/status and order/payment/* (stubbed here)
 *
 * @internal
 */
final class SalesPageTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const USER     = ['id' => 1, 'uid' => 'test-uid', 'email_address' => 'client@example.com', 'full_name' => 'Test Client',
        'avatar_path' => null, 'display_name' => null, 'preferred_language' => 'en-US'];
    private const BUSINESS = ['business_slug' => 'otter-shop', 'business_name' => 'Otter Shop', 'country_code' => 'TH', 'currency_code' => 'THB'];
    private const ROW      = ['public_id' => 'or0000000001', 'order_number' => 'IN2611-0001', 'created_at' => '2026-11-16 03:00:00',
        'customer_name' => 'Ann Lee', 'email_address' => 'ann@example.com', 'telephone_number' => '+66812345678', 'currency_code' => 'THB',
        'total_amount' => '600.00', 'line_count' => 2, 'order_status' => 'open', 'financial_status' => 'partially_paid',
        'fulfillment_status' => null, 'payment_method_name' => 'Cash', 'shipping_method_name' => null, 'cancelled_at' => null];

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
            $this->calls[] = [substr($url, strlen((string) getenv('LINK_INTERNAL_API'))), json_decode(json_encode($options['json'] ?? []), true)];
            [$code, $body] = array_shift($replies);
            return (new Response(new App()))->setStatusCode($code)->setBody(json_encode($body));
        });
        Services::injectMock('curlrequest', $curl);
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

    private static function order(array $more = []): array
    {
        return $more + [
            'public_id' => 'or0000000001', 'order_number' => 'IN2611-0001', 'created_at' => '2026-11-16 03:00:00', 'updated_at' => '2026-11-16 03:00:00',
            'paid_at' => null, 'cancelled_at' => null, 'language_code' => 'en-US', 'currency_code' => 'THB',
            'cancelled_by_name' => null, 'cancel_reason' => null, 'refund_due_amount' => '0.00', 'refund_closed_reason' => null,
            'no_show_at' => null, 'no_show_by_name' => null, 'can_cancel' => true, 'cancel_blocker' => null, 'can_mark_no_show' => false,
            'order_status' => 'in_progress', 'financial_status' => 'partially_paid', 'fulfillment_status' => 'open', 'has_products' => true,
            'customer' => ['first_name' => 'Ann', 'last_name' => 'Lee', 'email_address' => 'ann@example.com', 'telephone_number' => '+66812345678'],
            'lines' => [['line_number' => 1, 'kind' => 'P', 'artifact_public_id' => 'ar0000000001', 'artifact_name' => 'Otter <Book>', 'variant_label' => 'Hardcover',
                'sku' => 'BK-1', 'quantity' => 2, 'unit_price' => '300.00', 'compare_price' => null, 'discount_amount' => '0.00',
                'tax' => ['calculation' => 'I', 'percentage' => '7.00', 'amount' => '39.25'], 'line_total' => '600.00',
                'store' => ['public_id' => 'st0000000001', 'store_name' => 'Silom', 'timezone' => 'Asia/Bangkok'], 'starts_at' => null, 'ends_at' => null, 'participants' => []]],
            'shipping' => ['type' => 'S', 'name' => 'Pickup', 'address' => null, 'pickup_point' => null,
                'pickup_store' => ['public_id' => 'st0000000001', 'store_name' => 'Silom', 'timezone' => 'Asia/Bangkok']],
            'payment_method' => ['code' => 'cash', 'handler_code' => 'instruction', 'name' => 'Cash'], 'discount_code' => null,
            'totals' => ['subtotal' => '600.00', 'discount' => '0.00', 'shipping' => '0.00', 'tax_included' => '39.25', 'tax_added' => '0.00', 'total' => '600.00'],
            'money' => ['paid' => '250.00', 'refunded' => '0.00', 'net_paid' => '250.00', 'balance_due' => '350.00', 'refundable' => '250.00'],
            'payments' => [
                ['public_id' => 'pa0000000001', 'transaction_type' => 'payment', 'transaction_status' => 'pending', 'amount' => '600.00',
                    'payment_method_name' => 'Cash', 'reference' => null, 'note' => null, 'failure_reason' => null, 'recorded_by_name' => null,
                    'processed_at' => null, 'created_at' => '2026-11-16 03:00:00'],
                ['public_id' => 'pa0000000002', 'transaction_type' => 'payment', 'transaction_status' => 'succeeded', 'amount' => '250.00',
                    'payment_method_name' => 'Bank transfer', 'reference' => 'TX-1', 'note' => null, 'failure_reason' => null, 'recorded_by_name' => 'Somchai',
                    'processed_at' => '2026-11-16 05:00:00', 'created_at' => '2026-11-16 05:00:00'],
            ],
        ];
    }

    private static function detail(bool $canRefund, array $order = []): array
    {
        return [200, ['status' => 'success', 'data' => ['order' => self::order($order), 'timezone' => 'Asia/Bangkok', 'can_refund' => $canRefund,
            'payment_methods' => [['public_id' => 'pm0000000001', 'method_name' => 'Cash'], ['public_id' => 'pm0000000002', 'method_name' => 'Bank transfer']]]]];
    }

    public function testEveryoneSeesTheOrdersList(): void
    {
        $result = $this->withSession($this->session('E'))->get('admin/sales');
        $result->assertOK();
        $result->assertSee('sales-table');
        $result->assertSee('Order number, customer name, email or phone');
        $this->assertSame([], $this->calls);
    }

    public function testDataSendsTheSearchAndFiltersAndShowsTheRows(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => ['records_total' => 5, 'records_filtered' => 1, 'rows' => [self::ROW], 'timezone' => 'Asia/Bangkok']]]);
        $result = $this->withSession($this->session('E'))->post('admin/sales/data', ['draw' => 3, 'start' => 0, 'length' => 25,
            'filters' => ['search' => ' ann lee ', 'date_from' => '2026-11-01', 'date_to' => '', 'financial_status' => 'partially_paid', 'nope' => 'x']]);
        $result->assertOK();
        $this->assertSame(['v1/client/orders', ['business_slug' => 'otter-shop', 'start' => 0, 'length' => 25, 'search' => 'ann lee',
            'filters' => ['date_from' => '2026-11-01', 'financial_status' => 'partially_paid']]], $this->calls[0]);
        $json = $this->json($result);
        $this->assertSame([3, 5, 1], [$json['draw'], $json['recordsTotal'], $json['recordsFiltered']]);
        $row = $json['data'][0];
        $this->assertSame(['IN2611-0001', 'none', 'partially_paid', base_url('admin/sales/order/or0000000001')],
            [$row['order_number'], $row['fulfillment_status'], $row['financial_status'], $row['edit_url']]);
        $this->assertStringContainsString('ann@example.com', $row['customer']);
    }

    public function testTheOrderPageShowsItsLinesPaymentsAndForms(): void
    {
        $this->stubApi(self::detail(false));
        $result = $this->withSession($this->session('E'))->get('admin/sales/order/or0000000001');
        $result->assertOK();
        $this->assertSame(['v1/client/order', ['public_id' => 'or0000000001']], $this->calls[0]);
        foreach (['Order IN2611-0001', 'Otter &lt;Book&gt;', 'Hardcover', 'TX-1', 'Recorded by Somchai', 'Partially paid', 'In progress',
            'Record payment', 'data-settle="succeeded"', 'Bank transfer', 'As ordered: Cash', 'Store pickup'] as $text) {
            $result->assertSee($text);
        }
        $result->assertDontSee('Otter <Book>');
        // refunds are for the owners and managers
        $result->assertDontSee('data-payment-form="refund"');
    }

    public function testOwnersMayRefundAndAnOrderWithNothingToShipHasNoFulfillment(): void
    {
        $this->stubApi(self::detail(true, ['has_products' => false, 'fulfillment_status' => null, 'shipping' => null]));
        $result = $this->withSession($this->session('O'))->get('admin/sales/order/or0000000001');
        $result->assertSee('data-payment-form="refund"');
        $result->assertSee('This order has no products to ship.');
        $result->assertDontSee('id="fulfillment_status"');
    }

    public function testAnUnknownOrderIsNotFound(): void
    {
        $this->stubApi([404, ['status' => 'error', 'message' => 'Order not found.']]);
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $this->withSession($this->session('M'))->get('admin/sales/order/nosuchorder1');
    }

    public function testChangesAreRelayedToTheApi(): void
    {
        $this->stubApi(
            self::detail(true),
            self::detail(true),
            [422, ['status' => 'error', 'message' => 'Please correct the highlighted fields.', 'errors' => ['amount' => 'The amount is more than the balance due.']]],
        );
        $session = $this->session('M');
        $this->withSession($session)->post('admin/sales/order/status', ['public_id' => 'or0000000001', 'order_status' => 'closed', 'fulfillment_status' => ''])->assertOK();
        $this->withSession($session)->post('admin/sales/order/payment/settle', ['public_id' => 'pa0000000001', 'transaction_status' => 'voided'])->assertOK();
        $result = $this->withSession($session)->post('admin/sales/order/payment', ['public_id' => 'or0000000001', 'transaction_type' => 'payment',
            'transaction_status' => 'succeeded', 'amount' => '400', 'payment_method_public_id' => 'pm0000000002', 'reference' => '', 'note' => 'At the counter']);
        $result->assertStatus(422);
        $this->assertSame(['amount' => 'The amount is more than the balance due.'], $this->json($result)['errors']);

        $this->assertSame([
            ['v1/client/order/status', ['public_id' => 'or0000000001', 'order_status' => 'closed']],
            ['v1/client/order/payment/settle', ['public_id' => 'pa0000000001', 'transaction_status' => 'voided']],
            ['v1/client/order/payment/create', ['public_id' => 'or0000000001', 'payment' => ['transaction_type' => 'payment', 'transaction_status' => 'succeeded',
                'amount' => '400', 'payment_method_public_id' => 'pm0000000002', 'note' => 'At the counter']]],
        ], $this->calls);
    }

    public function testACancelledOrderShowsItsRefundToTheManagersOnly(): void
    {
        $cancelled = ['cancelled_at' => '2026-11-16 06:00:00', 'cancelled_by_name' => 'Somchai', 'cancel_reason' => 'Customer <changed> mind',
            'refund_due_amount' => '250.00', 'order_status' => 'closed', 'can_cancel' => false, 'cancel_blocker' => 'ended'];
        $this->stubApi(self::detail(true, $cancelled), self::detail(false, $cancelled));

        $result = $this->withSession($this->session('M'))->get('admin/sales/order/or0000000001');
        foreach (['Cancelled by Somchai', 'Reason: Customer &lt;changed&gt; mind', 'Refund due: ', 'Close without refund', 'data-payment-form="refund"'] as $text) {
            $result->assertSee($text);
        }
        $result->assertDontSee('data-bs-target="#sales-cancel-modal"');
        $this->assertStringContainsString('<fieldset disabled>', $result->getBody());

        $result = $this->withSession($this->session('E'))->get('admin/sales/order/or0000000001');
        $result->assertSee('An owner or manager will record the refund.');
        $result->assertDontSee('Close without refund');
    }

    public function testAnOrderThatCanBeCancelledOffersItAndAFlaggedCustomerShows(): void
    {
        $this->stubApi(self::detail(false, ['customer' => ['first_name' => 'Ann', 'last_name' => 'Lee', 'email_address' => 'ann@example.com',
            'telephone_number' => '+66812345678', 'no_show_count' => 2], 'can_mark_no_show' => true]));
        $result = $this->withSession($this->session('E'))->get('admin/sales/order/or0000000001');
        foreach (['data-bs-target="#sales-cancel-modal"', 'id="sales-cancel-form"', 'Return the products to stock', 'data-no-show', '2 unpaid no-shows'] as $text) {
            $result->assertSee($text);
        }
    }

    public function testCancelNoShowAndRefundCloseAreRelayed(): void
    {
        $this->stubApi(self::detail(false), self::detail(false), self::detail(true));
        $session = $this->session('E');
        $this->withSession($session)->post('admin/sales/order/cancel', ['public_id' => 'or0000000001', 'reason' => ' Out of stock ', 'restock' => '0'])->assertOK();
        $this->withSession($session)->post('admin/sales/order/no-show', ['public_id' => 'or0000000001'])->assertOK();
        $this->withSession($this->session('M'))->post('admin/sales/order/refund-close', ['public_id' => 'or0000000001', 'reason' => 'Fee kept'])->assertOK();
        $this->assertSame([
            ['v1/client/order/cancel', ['public_id' => 'or0000000001', 'reason' => 'Out of stock', 'restock' => false]],
            ['v1/client/order/no-show', ['public_id' => 'or0000000001']],
            ['v1/client/order/refund-close', ['public_id' => 'or0000000001', 'reason' => 'Fee kept']],
        ], $this->calls);
    }

    public function testTheListShowsCancelledOrdersAndTheirRefundDue(): void
    {
        $row = ['cancelled_at' => '2026-11-16 06:00:00', 'refund_due_amount' => '250.00', 'order_status' => 'closed'] + self::ROW;
        $this->stubApi([200, ['status' => 'success', 'data' => ['records_total' => 1, 'records_filtered' => 1, 'rows' => [$row], 'timezone' => 'Asia/Bangkok']]]);
        $result = $this->withSession($this->session('M'))->post('admin/sales/data', ['draw' => 1, 'start' => 0, 'length' => 25, 'filters' => ['needs_refund' => 'Y']]);
        $this->assertTrue($this->calls[0][1]['filters']['needs_refund']);
        $data = $this->json($result)['data'][0];
        $this->assertSame('cancelled', $data['order_status']);
        $this->assertStringContainsString('Refund due', $data['refund_due']);
    }

    public function testEveryoneCanOpenTheNewOrderPage(): void
    {
        $result = $this->withSession($this->session('E'))->get('admin/sales/new');
        $result->assertOK();
        foreach (['id="counter-order"', 'id="co-book-modal"', 'Handed over at the counter now', 'Paid now, in full', 'admin/sales/counter/'] as $text) {
            $this->assertStringContainsString($text, $result->getBody());
        }
        $this->assertSame([], $this->calls);
        // the list offers it
        $this->assertStringContainsString(base_url('admin/sales/new'), $this->withSession($this->session('E'))->get('admin/sales')->getBody());
    }

    public function testTheCounterCallsAreSentOnForTheSessionBusiness(): void
    {
        $this->stubApi(
            [200, ['status' => 'success', 'data' => ['items' => [['variant' => 'va0000000001', 'kind' => 'P']]]]],
            [409, ['status' => 'error', 'message' => 'Refused', 'errors' => ['shipping' => 'required'], 'data' => ['reason' => 'shipping']]],
            [201, ['status' => 'success', 'message' => 'Order created.', 'data' => ['public_id' => 'or0000000009', 'order_number' => 'IN2611-0009']]],
        );
        $session = $this->session('E');
        $result  = $this->withSession($session)->withBodyFormat('json')->post('admin/sales/counter/catalog', ['store_public_id' => 'st0000000001', 'search' => 'balm']);
        $result->assertOK();
        $this->assertSame('va0000000001', $this->json($result)['data']['items'][0]['variant']);

        $result = $this->withSession($session)->withBodyFormat('json')->post('admin/sales/counter/order', ['store_public_id' => 'st0000000001', 'handed_over' => false]);
        $result->assertStatus(409);
        $this->assertSame(['shipping' => 'required'], $this->json($result)['errors']);
        $result = $this->withSession($session)->withBodyFormat('json')->post('admin/sales/counter/order', ['store_public_id' => 'st0000000001']);
        $result->assertStatus(201);
        $this->assertSame('or0000000009', $this->json($result)['data']['public_id']);

        $this->assertSame([
            ['v1/client/counter/catalog', ['business_slug' => 'otter-shop', 'store_public_id' => 'st0000000001', 'search' => 'balm']],
            ['v1/client/counter/order', ['business_slug' => 'otter-shop', 'store_public_id' => 'st0000000001', 'handed_over' => false]],
            ['v1/client/counter/order', ['business_slug' => 'otter-shop', 'store_public_id' => 'st0000000001']],
        ], $this->calls);
    }

    public function testOnlyTheCounterCallsGoThrough(): void
    {
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $this->withSession($this->session('E'))->withBodyFormat('json')->post('admin/sales/counter/delete-all', []);
    }

    public function testACounterOrderSaysWhereAndByWhom(): void
    {
        $this->stubApi(self::detail(false, ['order_source' => 'counter', 'created_by_name' => 'Nok', 'counter_store' => ['public_id' => 'st0000000001', 'store_name' => 'Silom', 'timezone' => 'Asia/Bangkok']]));
        $this->withSession($this->session('E'))->get('admin/sales/order/or0000000001')->assertSee('At the counter, Silom · by Nok');
    }

    private static function appointment(array $reschedule): array
    {
        return ['line_number' => 2, 'kind' => 'A', 'artifact_public_id' => 'ar0000000002', 'artifact_name' => 'Thai massage', 'variant_label' => null,
            'sku' => null, 'quantity' => 1, 'unit_price' => '600.00', 'compare_price' => null, 'discount_amount' => '0.00',
            'tax' => ['calculation' => 'X', 'percentage' => null, 'amount' => '0.00'], 'line_total' => '600.00',
            'store' => ['public_id' => 'st0000000001', 'store_name' => 'Silom', 'timezone' => 'Asia/Bangkok'],
            'starts_at' => '2026-11-18 04:00:00', 'ends_at' => '2026-11-18 05:00:00', 'participants' => ['Ann'],
            'staff' => [['public_id' => 'pe0000000001', 'name' => 'Somsak']], 'reschedule' => $reschedule];
    }

    public function testAnAppointmentCanBeMovedUntilItsDeadline(): void
    {
        $line = self::order()['lines'][0];
        $this->stubApi(
            self::detail(false, ['lines' => [$line, self::appointment(['allowed' => true, 'blocker' => null, 'until' => '2026-11-17 04:00:00'])]]),
            self::detail(false, ['lines' => [$line, self::appointment(['allowed' => false, 'blocker' => 'deadline', 'until' => '2026-11-17 04:00:00'])]]),
        );
        $result = $this->withSession($this->session('E'))->get('admin/sales/order/or0000000001');
        foreach (['Somsak', 'data-reschedule="2"', 'Change time or staff', 'Can be moved until', '2026-11-17 04:00:00 UTC', 'id="sales-reschedule-modal"'] as $text) {
            $result->assertSee($text);
        }
        $result = $this->withSession($this->session('E'))->get('admin/sales/order/or0000000001');
        $result->assertSee('Past the cancellation deadline: it can no longer be moved.');
        $result->assertDontSee('data-reschedule="2"');
    }

    public function testMovingIsRelayedToTheApi(): void
    {
        $this->stubApi(
            [200, ['status' => 'success', 'data' => ['date' => '2026-11-18', 'until' => '2026-11-17 04:00:00', 'staff' => [], 'slots' => [['time' => '14:00']]]]],
            [409, ['status' => 'error', 'message' => 'This time is no longer free with these people. Please choose another.']],
        );
        $session = $this->session('E');
        $result  = $this->withSession($session)->withBodyFormat('json')->post('admin/sales/order/reschedule-slots', ['public_id' => 'or0000000001', 'line_number' => 2, 'date' => '2026-11-18']);
        $this->assertSame('14:00', $this->json($result)['data']['slots'][0]['time']);
        $result = $this->withSession($session)->withBodyFormat('json')->post('admin/sales/order/reschedule', ['public_id' => 'or0000000001', 'line_number' => 2,
            'starts_at' => '2026-11-18T14:00:00+07:00', 'staff' => ['pe0000000001']]);
        $result->assertStatus(409);
        $this->assertSame([
            ['v1/client/order/reschedule/slots', ['public_id' => 'or0000000001', 'line_number' => 2, 'date' => '2026-11-18']],
            ['v1/client/order/reschedule', ['public_id' => 'or0000000001', 'line_number' => 2, 'starts_at' => '2026-11-18T14:00:00+07:00', 'staff' => ['pe0000000001']]],
        ], $this->calls);
    }

    public function testAReportedPaymentShowsItsSlipToCheck(): void
    {
        $payments = self::order()['payments'];
        $payments[0] += ['reported_at' => '2026-11-16 03:05:00', 'has_slip' => true];
        $this->stubApi(self::detail(false, ['payments' => $payments]));
        $result = $this->withSession($this->session('E'))->get('admin/sales/order/or0000000001');
        foreach (['The customer says they paid,', 'View slip', base_url('admin/sales/payment-slip/pa0000000001'), 'data-settle="succeeded" data-payment-id="pa0000000001"'] as $text) {
            $result->assertSee($text);
        }
    }

    public function testTheSlipIsShownAsTheApiGivesIt(): void
    {
        $curl = $this->createMock(CURLRequest::class);
        $curl->method('get')->willReturnCallback(function (string $url) {
            $this->calls[] = [substr($url, strlen((string) getenv('LINK_INTERNAL_API'))), []];
            return (new Response(new App()))->setStatusCode(200)->setContentType('image/png', '')->setBody('PNGDATA');
        });
        Services::injectMock('curlrequest', $curl);
        $result = $this->withSession($this->session('E'))->get('admin/sales/payment-slip/pa0000000001');
        $result->assertOK();
        $this->assertSame('PNGDATA', $result->response()->getBody());
        $this->assertStringStartsWith('image/png', $result->response()->getHeaderLine('Content-Type'));
        $this->assertSame('v1/client/order/payment-slip/pa0000000001', $this->calls[0][0]);
    }
}
