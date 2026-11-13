<?php

use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\App;

/**
 * The payment methods of the session business (admin/business/payment-methods/*): its owners and managers only,
 * through v1/client/payment-methods, payment-method/create and payment-method/edit (stubbed here)
 *
 * @internal
 */
final class PaymentMethodsPageTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const USER     = ['id' => 1, 'uid' => 'test-uid', 'email_address' => 'client@example.com', 'full_name' => 'Test Client',
        'avatar_path' => null, 'display_name' => null, 'preferred_language' => 'en-US'];
    private const BUSINESS = ['business_slug' => 'otter-spa', 'business_name' => 'Otter Spa', 'country_code' => 'TH', 'currency_code' => 'THB'];
    private const STORES   = [200, ['status' => 'success', 'data' => ['stores' => [
        ['public_id' => 'st0re1', 'store_name' => 'Bangkok', 'store_type' => 'P', 'translations' => ['th' => ['store_name' => 'กรุงเทพ']]],
        ['public_id' => 'st0re2', 'store_name' => 'Pattaya', 'store_type' => 'P', 'translations' => []],
    ]]]];
    private const OPTIONS  = ['methods' => ['cash', 'bank_transfer', 'promptpay', 'other'], 'handlers' => [
        'instruction'  => ['methods' => ['cash', 'bank_transfer', 'promptpay', 'other'], 'confirmation' => 'M', 'instruction_required' => true, 'settings' => []],
        'promptpay_qr' => ['methods' => ['promptpay'], 'confirmation' => 'M', 'instruction_required' => false, 'settings' => ['promptpay_id']],
    ]];
    private const QR       = ['public_id' => 'pm0000000001', 'store_public_id' => 'st0re1', 'method_code' => 'promptpay', 'handler_code' => 'promptpay_qr',
        'confirmation' => 'M', 'method_name' => 'PromptPay QR', 'instruction' => null, 'settings' => ['promptpay_id' => '0812345678'],
        'is_active' => 'Y', 'sort_order' => 1, 'translations' => ['th' => ['method_name' => 'พร้อมเพย์', 'instruction' => null],
            'ja' => ['method_name' => 'プロンプトペイ', 'instruction' => null]]];
    private const CASH     = ['public_id' => 'pm0000000002', 'store_public_id' => null, 'method_code' => 'cash', 'handler_code' => 'instruction',
        'confirmation' => 'M', 'method_name' => 'Cash', 'instruction' => 'Pay at the counter', 'settings' => [],
        'is_active' => 'N', 'sort_order' => 0, 'translations' => []];

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
        return [200, ['status' => 'success', 'data' => ['payment_methods' => $methods, 'options' => self::OPTIONS]]];
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

    public function testEmployeesCannotManagePaymentMethods(): void
    {
        foreach (['admin/business/payment-methods', 'admin/business/payment-methods/create'] as $page) {
            $this->withSession($this->session('E'))->get($page)->assertRedirectTo(base_url('admin/dashboard'));
        }
        $this->withSession($this->session('E'))->post('admin/business/payment-methods/save', ['method_code' => 'cash'])->assertStatus(403);
        $this->assertSame([], $this->calls);

        $this->withSession($this->session('E'))->get('admin/dashboard')->assertDontSee('admin/business/payment-methods');
        $this->withSession($this->session('M'))->get('admin/dashboard')->assertSee('admin/business/payment-methods');
    }

    public function testListShowsTheStoreOrAllStores(): void
    {
        $this->stubApi(self::methods(self::CASH, self::QR), self::STORES);
        $result = $this->withSession($this->session('O'))->post('admin/business/payment-methods/data?store=st0re1', ['draw' => 1, 'start' => 0, 'length' => 10]);
        $result->assertOK();
        $this->assertSame(['v1/client/payment-methods', ['business_slug' => 'otter-spa', 'store_public_id' => 'st0re1']], $this->calls[0]);
        $rows = $this->json($result)['data'];
        $this->assertSame(['Cash', 'PromptPay QR'], array_column($rows, 'method_name'));
        $this->assertSame(['All stores', 'Bangkok'], array_column($rows, 'store_name'));
        $this->assertSame(['Cash', 'PromptPay'], array_column($rows, 'method_code'));
    }

    public function testFormOffersTheApisOptionsAndTheStores(): void
    {
        $this->stubApi(self::methods(self::QR), self::STORES);
        $result = $this->withSession($this->session('M'))->get('admin/business/payment-methods/edit/pm0000000001');
        $result->assertOK();
        foreach (['All stores', 'Bangkok', 'Pattaya', 'Bank Transfer', 'PromptPay QR code', 'PromptPay ID', '0812345678', 'Name in ภาษาไทย (Thai)', 'พร้อมเพย์', '"promptpay_qr":{"methods":["promptpay"]'] as $text) {
            $result->assertSee($text);
        }
    }

    public function testAnUnknownMethodIsNotFound(): void
    {
        $this->stubApi(self::methods());
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $this->withSession($this->session('M'))->get('admin/business/payment-methods/edit/nosuchmethod');
    }

    public function testCreateSendsEveryStoreAsNullAndOnlyTheHandlersSettings(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => ['public_id' => 'pm0000000003']]]);
        $result = $this->withSession($this->session('O'))->post('admin/business/payment-methods/save', ['public_id' => '', 'store_public_id' => '',
            'method_code' => 'cash', 'handler_code' => 'instruction', 'method_name' => ' Cash ', 'instruction' => 'Pay at the counter',
            'promptpay_id' => '0812345678', 'is_active' => 'Y', 'sort_order' => '0',
            'translation_name_th' => 'เงินสด', 'translation_instruction_th' => 'ชำระที่เคาน์เตอร์']);
        $result->assertOK();
        $this->assertSame(['v1/client/payment-method/create', [
            'business_slug'  => 'otter-spa',
            'payment_method' => ['method_code' => 'cash', 'handler_code' => 'instruction', 'method_name' => 'Cash', 'instruction' => 'Pay at the counter',
                'is_active' => 'Y', 'sort_order' => '0', 'store_public_id' => null, 'settings' => []],
            'translations'   => ['en-US' => ['method_name' => '', 'instruction' => ''], 'th' => ['method_name' => 'เงินสด', 'instruction' => 'ชำระที่เคาน์เตอร์']],
        ]], $this->calls[0]);
        $this->assertSame(base_url('admin/business/payment-methods/edit/pm0000000003'), $this->json($result)['redirect']);
    }

    public function testEditKeepsOtherLanguagesAndSendsThePromptPayIdWithoutDashes(): void
    {
        $this->stubApi(self::methods(self::QR), [200, ['status' => 'success', 'data' => ['public_id' => 'pm0000000001']]]);
        $this->withSession($this->session('M'))->post('admin/business/payment-methods/save', ['public_id' => 'pm0000000001', 'store_public_id' => 'st0re1',
            'method_code' => 'promptpay', 'handler_code' => 'promptpay_qr', 'method_name' => 'PromptPay QR', 'instruction' => '',
            'promptpay_id' => '081-234 5678', 'is_active' => 'Y', 'sort_order' => '1', 'translation_name_th' => 'พร้อมเพย์ QR'])->assertOK();
        [$endpoint, $payload] = $this->calls[1];
        $this->assertSame('v1/client/payment-method/edit', $endpoint);
        $this->assertSame(['public_id' => 'pm0000000001', 'store_public_id' => 'st0re1', 'settings' => ['promptpay_id' => '0812345678']],
            ['public_id' => $payload['public_id'], 'store_public_id' => $payload['payment_method']['store_public_id'], 'settings' => $payload['payment_method']['settings']]);
        $this->assertSame(['th', 'ja', 'en-US'], array_keys($payload['translations'])); // ja is not a web page language, but is kept
        $this->assertSame('พร้อมเพย์ QR', $payload['translations']['th']['method_name']);
    }

    public function testApiErrorsAreRenamedForTheForm(): void
    {
        $this->stubApi([422, ['status' => 'error', 'message' => 'Please correct the highlighted fields.', 'errors' => [
            'settings.promptpay_id'            => 'Please enter a PromptPay ID.',
            'translations.th.method_name'      => 'Please enter the name in this language too.',
            'translations.th.instruction'      => 'This can be at most 2000 characters.',
            'method_name'                      => 'Another payment method shown with this one already has this name.',
        ]]]);
        $result = $this->withSession($this->session('O'))->post('admin/business/payment-methods/save', ['method_code' => 'promptpay', 'handler_code' => 'promptpay_qr']);
        $result->assertStatus(422);
        $this->assertSame(['promptpay_id', 'translation_name_th', 'translation_instruction_th', 'method_name'], array_keys($this->json($result)['errors']));
    }
}
