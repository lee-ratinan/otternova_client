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
}
