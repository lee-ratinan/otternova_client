<?php

use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\App;

/**
 * The shipping methods of the session business (admin/business/shipping-methods/*): its owners and managers only,
 * through v1/client/shipping-methods, shipping-method/create and shipping-method/edit (stubbed here)
 *
 * @internal
 */
final class ShippingMethodsPageTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const USER     = ['id' => 1, 'uid' => 'test-uid', 'email_address' => 'client@example.com', 'full_name' => 'Test Client',
        'avatar_path' => null, 'display_name' => null, 'preferred_language' => 'en-US'];
    private const BUSINESS = ['business_slug' => 'otter-spa', 'business_name' => 'Otter Spa', 'country_code' => 'TH', 'currency_code' => 'THB'];
    private const STORES   = [200, ['status' => 'success', 'data' => ['stores' => [
        ['public_id' => 'st0re1', 'store_name' => 'Bangkok', 'store_type' => 'P', 'translations' => ['th' => ['store_name' => 'กรุงเทพ']]],
        ['public_id' => 'st0re2', 'store_name' => 'Pattaya', 'store_type' => 'P', 'translations' => []],
    ]]]];
    private const KERRY    = ['public_id' => 'sm0000000001', 'store_public_id' => null, 'method_type' => 'H', 'method_name' => 'Kerry Express',
        'description' => null, 'fee_basis' => 'W', 'free_over_amount' => '1000.00', 'eta_days_min' => 1, 'eta_days_max' => 3, 'is_active' => 'Y', 'sort_order' => 0,
        'rates' => [
            ['range_from' => '0.00', 'range_to' => '1000.00', 'fee_amount' => '50.00', 'step_size' => null, 'step_fee' => null],
            ['range_from' => '1000.00', 'range_to' => null, 'fee_amount' => '50.00', 'step_size' => '1000.00', 'step_fee' => '15.00'],
        ],
        'translations' => ['th' => ['method_name' => 'เคอรี่', 'description' => null], 'ja' => ['method_name' => 'ケリー', 'description' => null]]];
    private const PICKUP   = ['public_id' => 'sm0000000002', 'store_public_id' => 'st0re1', 'method_type' => 'S', 'method_name' => 'Pick up at the store',
        'description' => null, 'fee_basis' => 'P', 'free_over_amount' => null, 'eta_days_min' => null, 'eta_days_max' => null, 'is_active' => 'N', 'sort_order' => 1,
        'rates' => [], 'translations' => []];

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
            $endpoint = substr($url, strlen((string) getenv('LINK_INTERNAL_API')));
            // the languages of the web pages, which the form asks for (websiteLanguages()), answered here and not recorded
            if ('v1/client/website' === $endpoint) {
                return (new Response(new App()))->setStatusCode(200)->setBody(json_encode(['status' => 'success',
                    'data' => ['languages' => [['language_code' => 'en-US'], ['language_code' => 'th']]]]));
            }
            // as the API reads it: the empty objects sent as (object) arrive as arrays
            $this->calls[] = [$endpoint, json_decode(json_encode($options['json'] ?? []), true)];
            [$code, $body] = array_shift($replies);
            return (new Response(new App()))->setStatusCode($code)->setBody(json_encode($body));
        });
        Services::injectMock('curlrequest', $curl);
    }

    private static function methods(array ...$methods): array
    {
        return [200, ['status' => 'success', 'data' => ['shipping_methods' => $methods, 'options' => ['method_types' => ['H', 'L', 'S'], 'fee_bases' => ['P', 'W'], 'rates_max' => 50]]]];
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

    public function testEmployeesCannotManageShippingMethods(): void
    {
        foreach (['admin/business/shipping-methods', 'admin/business/shipping-methods/create'] as $page) {
            $this->withSession($this->session('E'))->get($page)->assertRedirectTo(base_url('admin/dashboard'));
        }
        $this->withSession($this->session('E'))->post('admin/business/shipping-methods/save', ['method_type' => 'H'])->assertStatus(403);
        $this->assertSame([], $this->calls);

        $this->withSession($this->session('E'))->get('admin/dashboard')->assertDontSee('admin/business/shipping-methods');
        $this->withSession($this->session('M'))->get('admin/dashboard')->assertSee('admin/business/shipping-methods');
    }

    public function testListShowsTheStoreAndTheFee(): void
    {
        $this->stubApi(self::methods(self::KERRY, self::PICKUP), self::STORES);
        $result = $this->withSession($this->session('O'))->post('admin/business/shipping-methods/data', ['draw' => 1, 'start' => 0, 'length' => 10]);
        $result->assertOK();
        $this->assertSame(['v1/client/shipping-methods', ['business_slug' => 'otter-spa']], $this->calls[0]);
        $rows = $this->json($result)['data'];
        $this->assertSame(['Kerry Express', 'Pick up at the store'], array_column($rows, 'method_name'));
        $this->assertSame(['All stores', 'Bangkok'], array_column($rows, 'store_name'));
        $this->assertSame(['Home delivery', 'Store pickup'], array_column($rows, 'method_type'));
        $this->assertSame(['By weight (2 brackets) · free from 1,000.00 THB', 'Free'], array_column($rows, 'fee'));
    }

    public function testFormShowsTheBrackets(): void
    {
        $this->stubApi(self::methods(self::KERRY), self::STORES);
        $result = $this->withSession($this->session('M'))->get('admin/business/shipping-methods/edit/sm0000000001');
        $result->assertOK();
        foreach (['All stores', 'Bangkok', 'Locker or pickup point', 'Free Shipping From (THB)', '1,000.00 THB', 'Name in ภาษาไทย (Thai)', 'เคอรี่',
            '0 g to under 1,000 g: 50.00 THB', '1,000 g and more: 50.00 THB + 15.00 THB for every 1,000 g or part of it above the start',
            'name="rates_json"', '"step_fee":"15.00"'] as $text) {
            $result->assertSee($text);
        }
    }

    public function testAnUnknownMethodIsNotFound(): void
    {
        $this->stubApi(self::methods());
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $this->withSession($this->session('M'))->get('admin/business/shipping-methods/edit/nosuchmethod');
    }

    public function testCreateSendsEmptyFieldsAsNullAndTheBrackets(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => ['public_id' => 'sm0000000003']]]);
        $result = $this->withSession($this->session('O'))->post('admin/business/shipping-methods/save', ['public_id' => '', 'store_public_id' => '',
            'method_type' => 'H', 'method_name' => ' Flash ', 'description' => '', 'fee_basis' => 'P', 'free_over_amount' => '999',
            'eta_days_min' => '', 'eta_days_max' => '2', 'is_active' => 'Y', 'sort_order' => '0',
            'rates_json' => json_encode([['range_from' => null, 'range_to' => '500', 'fee_amount' => '60', 'step_size' => null, 'step_fee' => null],
                ['range_from' => '500', 'range_to' => '', 'fee_amount' => '40', 'step_size' => '', 'step_fee' => '']]),
            'translation_name_th' => 'แฟลช']);
        $result->assertOK();
        $this->assertSame(['v1/client/shipping-method/create', [
            'business_slug'   => 'otter-spa',
            'shipping_method' => ['method_type' => 'H', 'method_name' => 'Flash', 'description' => null, 'fee_basis' => 'P', 'free_over_amount' => '999',
                'eta_days_min' => null, 'eta_days_max' => '2', 'is_active' => 'Y', 'sort_order' => '0', 'store_public_id' => null],
            'rates'           => [
                ['range_from' => null, 'range_to' => '500', 'fee_amount' => '60', 'step_size' => null, 'step_fee' => null],
                ['range_from' => '500', 'range_to' => null, 'fee_amount' => '40', 'step_size' => null, 'step_fee' => null],
            ],
            'translations'    => ['en-US' => ['method_name' => '', 'description' => ''], 'th' => ['method_name' => 'แฟลช', 'description' => '']],
        ]], $this->calls[0]);
        $this->assertSame(base_url('admin/business/shipping-methods/edit/sm0000000003'), $this->json($result)['redirect']);
    }

    public function testEditKeepsOtherLanguages(): void
    {
        $this->stubApi(self::methods(self::KERRY), [200, ['status' => 'success', 'data' => ['public_id' => 'sm0000000001']]]);
        $this->withSession($this->session('M'))->post('admin/business/shipping-methods/save', ['public_id' => 'sm0000000001', 'store_public_id' => '',
            'method_type' => 'H', 'method_name' => 'Kerry Express', 'fee_basis' => 'W', 'is_active' => 'Y', 'sort_order' => '0', 'rates_json' => '[]',
            'translation_name_th' => 'เคอรี่ เอ็กซ์เพรส'])->assertOK();
        [$endpoint, $payload] = $this->calls[1];
        $this->assertSame('v1/client/shipping-method/edit', $endpoint);
        $this->assertSame([], $payload['rates']);
        $this->assertSame(['th', 'ja', 'en-US'], array_keys($payload['translations']));
        $this->assertSame('เคอรี่ เอ็กซ์เพรส', $payload['translations']['th']['method_name']);
    }

    public function testUnreadableBracketsAreRefused(): void
    {
        $this->stubApi();
        $this->withSession($this->session('O'))->post('admin/business/shipping-methods/save', ['method_type' => 'H', 'rates_json' => '{"a":1}'])->assertStatus(422);
        $this->assertSame([], $this->calls);
    }

    public function testApiErrorsAreRenamedForTheForm(): void
    {
        $this->stubApi([422, ['status' => 'error', 'message' => 'Please correct the highlighted fields.', 'errors' => [
            'translations.th.description' => 'This can be at most 2000 characters.',
            'rates.1.range_from'          => 'This bracket overlaps the one before it.',
            'rates'                       => 'Please enter the fee brackets again.',
            'method_name'                 => 'Another shipping method shown with this one already has this name.',
        ]]]);
        $result = $this->withSession($this->session('O'))->post('admin/business/shipping-methods/save', ['method_type' => 'H', 'rates_json' => '[]']);
        $result->assertStatus(422);
        $errors = $this->json($result)['errors'];
        $this->assertSame(['translation_description_th', 'rates.1.range_from', 'rates', 'method_name'], array_keys($errors));
        $this->assertSame('Fee bracket 2 (From): This bracket overlaps the one before it.', $errors['rates.1.range_from']);
    }
}
