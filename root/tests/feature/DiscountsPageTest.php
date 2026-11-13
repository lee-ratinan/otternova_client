<?php

use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\App;

/**
 * The discount codes of the session business (admin/business/discounts/*): its owners and managers only, through
 * v1/client/discounts, discount/create and discount/edit (stubbed here)
 *
 * @internal
 */
final class DiscountsPageTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const USER     = ['id' => 1, 'uid' => 'test-uid', 'email_address' => 'client@example.com', 'full_name' => 'Test Client',
        'avatar_path' => null, 'display_name' => null, 'preferred_language' => 'en-US'];
    private const BUSINESS = ['business_slug' => 'otter-spa', 'business_name' => 'Otter Spa', 'country_code' => 'TH', 'currency_code' => 'THB'];
    private const WELCOME  = ['public_id' => 'dc0000000001', 'discount_code' => 'WELCOME10', 'description' => 'Welcome', 'discount_type' => 'P',
        'discount_value' => '10.00', 'applies_to' => 'A', 'min_subtotal' => null, 'max_redemptions' => null, 'redemption_count' => 3,
        'valid_from' => null, 'expires_at' => null, 'is_active' => 'Y'];
    private const SPA      = ['public_id' => 'dc0000000002', 'discount_code' => 'SPA100', 'description' => null, 'discount_type' => 'F',
        'discount_value' => '100.00', 'applies_to' => 'S', 'min_subtotal' => '500.00', 'max_redemptions' => 5, 'redemption_count' => 5,
        'valid_from' => '2026-01-01 00:00:00', 'expires_at' => '2099-12-31 17:00:00', 'is_active' => 'Y'];
    private const OLD      = ['public_id' => 'dc0000000003', 'discount_code' => 'OLD', 'description' => null, 'discount_type' => 'F',
        'discount_value' => '20.00', 'applies_to' => 'P', 'min_subtotal' => null, 'max_redemptions' => null, 'redemption_count' => 0,
        'valid_from' => null, 'expires_at' => '2020-01-01 00:00:00', 'is_active' => 'Y'];
    private const LATER    = ['public_id' => 'dc0000000004', 'discount_code' => 'LATER', 'description' => null, 'discount_type' => 'P',
        'discount_value' => '12.50', 'applies_to' => 'A', 'min_subtotal' => null, 'max_redemptions' => null, 'redemption_count' => 0,
        'valid_from' => '2099-01-01 00:00:00', 'expires_at' => null, 'is_active' => 'Y'];

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

    private static function discounts(array ...$discounts): array
    {
        return [200, ['status' => 'success', 'data' => ['discounts' => $discounts, 'currency_code' => 'THB']]];
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

    public function testEmployeesCannotManageDiscounts(): void
    {
        foreach (['admin/business/discounts', 'admin/business/discounts/create'] as $page) {
            $this->withSession($this->session('E'))->get($page)->assertRedirectTo(base_url('admin/dashboard'));
        }
        $this->withSession($this->session('E'))->post('admin/business/discounts/save', ['discount_code' => 'X'])->assertStatus(403);
        $this->assertSame([], $this->calls);

        $this->withSession($this->session('E'))->get('admin/dashboard')->assertDontSee('admin/business/discounts');
        $this->withSession($this->session('M'))->get('admin/dashboard')->assertSee('admin/business/discounts');
    }

    public function testListShowsValuesUsageAndStatus(): void
    {
        $this->stubApi(self::discounts(self::OLD, self::SPA, self::WELCOME, self::LATER));
        $result = $this->withSession($this->session('O'))->post('admin/business/discounts/data', ['draw' => 1, 'start' => 0, 'length' => 10]);
        $result->assertOK();
        $this->assertSame(['v1/client/discounts', ['business_slug' => 'otter-spa']], $this->calls[0]);
        $rows = $this->json($result)['data'];
        $this->assertSame(['LATER', 'OLD', 'SPA100', 'WELCOME10'], array_column($rows, 'discount_code'));
        $this->assertSame(['12.5%', '20.00 THB', '100.00 THB', '10%'], array_column($rows, 'discount_value'));
        $this->assertSame(['0', '0', '5 / 5', '3'], array_column($rows, 'usage'));
        $this->assertSame(['scheduled', 'expired', 'used-up', 'active'], array_column($rows, 'status'));
    }

    public function testFormShowsTheSavedDiscount(): void
    {
        $this->stubApi(self::discounts(self::SPA));
        $result = $this->withSession($this->session('M'))->get('admin/business/discounts/edit/dc0000000002');
        $result->assertOK();
        foreach (['SPA100', 'Fixed amount', 'Services only', '500.00 THB', 'Minimum Subtotal (THB)', 'Times Used', '2099-12-31 17:00:00 UTC', '"utc":{"valid_from":"2026-01-01 00:00:00","expires_at":"2099-12-31 17:00:00"}'] as $text) {
            $result->assertSee($text);
        }
    }

    public function testAnUnknownDiscountIsNotFound(): void
    {
        $this->stubApi(self::discounts());
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $this->withSession($this->session('M'))->get('admin/business/discounts/edit/nosuchdisc');
    }

    public function testCreateSendsEmptyLimitsAsNullAndLocalTimesInUtc(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => ['public_id' => 'dc0000000005']]]);
        $result = $this->withSession($this->session('O'))->post('admin/business/discounts/save', ['public_id' => '', 'discount_code' => ' welcome10 ',
            'description' => '', 'discount_type' => 'P', 'discount_value' => '10', 'applies_to' => 'A', 'is_active' => 'Y',
            'min_subtotal' => '', 'max_redemptions' => '', 'valid_from' => '2026-12-01T09:00', 'expires_at' => '', 'timezone' => 'Asia/Bangkok']);
        $result->assertOK();
        $this->assertSame(['v1/client/discount/create', [
            'business_slug' => 'otter-spa',
            'discount'      => ['discount_code' => 'WELCOME10', 'description' => null, 'discount_type' => 'P', 'discount_value' => '10', 'applies_to' => 'A',
                'min_subtotal' => null, 'max_redemptions' => null, 'is_active' => 'Y', 'valid_from' => '2026-12-01 02:00:00', 'expires_at' => null],
        ]], $this->calls[0]);
        $this->assertSame(base_url('admin/business/discounts/edit/dc0000000005'), $this->json($result)['redirect']);
    }

    public function testEditWithAnUnknownTimezoneSendsTheTimesAsUtc(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => ['public_id' => 'dc0000000002']]]);
        $this->withSession($this->session('M'))->post('admin/business/discounts/save', ['public_id' => 'dc0000000002', 'discount_code' => 'SPA100',
            'discount_type' => 'F', 'discount_value' => '100', 'applies_to' => 'S', 'is_active' => 'N', 'min_subtotal' => '500', 'max_redemptions' => '5',
            'valid_from' => '', 'expires_at' => '2099-12-31T17:00', 'timezone' => 'Nowhere/Land'])->assertOK();
        [$endpoint, $payload] = $this->calls[0];
        $this->assertSame('v1/client/discount/edit', $endpoint);
        $this->assertSame('dc0000000002', $payload['public_id']);
        $this->assertSame(['500', '5', 'N', null, '2099-12-31 17:00:00'], [$payload['discount']['min_subtotal'], $payload['discount']['max_redemptions'],
            $payload['discount']['is_active'], $payload['discount']['valid_from'], $payload['discount']['expires_at']]);
    }

    public function testApiErrorsReachTheirFields(): void
    {
        $this->stubApi([422, ['status' => 'error', 'message' => 'Please correct the highlighted fields.', 'errors' => [
            'discount_code'   => 'Another discount of this business already has this code.',
            'max_redemptions' => 'This discount has already been used 4 times; the maximum cannot be lower.',
        ]]]);
        $result = $this->withSession($this->session('O'))->post('admin/business/discounts/save', ['discount_code' => 'TEN']);
        $result->assertStatus(422);
        $this->assertSame(['discount_code', 'max_redemptions'], array_keys($this->json($result)['errors']));
    }
}
