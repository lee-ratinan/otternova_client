<?php

use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\App;

/**
 * The session business's edit page and its stores (admin/business/*): its owners and managers only, through
 * v1/client/business/switch, business/edit, stores, store/create and store/edit (stubbed here).
 *
 * @internal
 */
final class BusinessManagementTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const USER = ['id' => 1, 'uid' => 'test-uid', 'email_address' => 'client@example.com', 'full_name' => 'Test Client',
        'avatar_path' => null, 'display_name' => null, 'preferred_language' => 'en-US'];
    private const BUSINESS = ['business_slug' => 'otter-cafe', 'business_name' => 'Otter Cafe', 'business_type_public_id' => 'aB3d',
        'contact_email_address' => 'hello@otter.cafe', 'contact_subdivision_code' => 'TH-10', 'country_code' => 'TH', 'currency_code' => 'THB'];
    private const TYPES = ['rows' => [['public_id' => 'aB3d', 'type_code' => 'cafe', 'translations' => ['en-US' => 'Café']]]];
    private const STORES = ['stores' => [
        ['public_id' => 'st0re1', 'store_name' => 'Bangkok', 'store_type' => 'P', 'store_phone_number' => '+66812345678',
            'store_address_line_1' => '1 Rama Rd', 'store_address_line_2' => null, 'store_city' => 'Pathum Wan',
            'store_subdivision_code' => 'TH-10', 'store_postal_code' => '10330', 'translations' => ['th' => 'กรุงเทพ', 'ja' => 'バンコク']],
        ['public_id' => 'st0re2', 'store_name' => 'Online Store', 'store_type' => 'O', 'store_phone_number' => null,
            'store_address_line_1' => null, 'store_address_line_2' => null, 'store_city' => null,
            'store_subdivision_code' => null, 'store_postal_code' => null, 'translations' => []],
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
            $this->calls[] = [substr($url, strlen((string) getenv('LINK_INTERNAL_API'))), $options['json'] ?? []];
            [$code, $body] = array_shift($replies);
            return (new Response(new App()))->setStatusCode($code)->setBody(json_encode($body));
        });
        Services::injectMock('curlrequest', $curl);
    }

    private function session(string $role, array $business = self::BUSINESS): array
    {
        return ['access_token' => 'test-token', 'expires_at' => time() + 3600, 'user' => self::USER,
            'business' => ['client_role' => $role, 'client_status' => 'A', 'is_default_business' => 'Y', 'business' => $business]];
    }

    private function switched(array $business = self::BUSINESS, array $names = ['th' => 'ออตเตอร์คาเฟ่', 'ja' => 'オッターカフェ']): array
    {
        return [200, ['status' => 'success', 'data' => ['business' => ['client_role' => 'O', 'client_status' => 'A',
            'is_default_business' => 'Y', 'business' => $business], 'translations' => $names]]];
    }

    private function json($result): array
    {
        return json_decode($result->getJSON(), true);
    }

    public function testEmployeesCannotManageTheBusiness(): void
    {
        foreach (['admin/business', 'admin/business/stores', 'admin/business/stores/create'] as $page) {
            $this->withSession($this->session('E'))->get($page)->assertRedirectTo(base_url('admin/dashboard'));
        }
        $result = $this->withSession($this->session('E'))->post('admin/business/save', ['business_name' => 'x']);
        $result->assertStatus(403);
        $this->assertSame([], $this->calls);

        // and the sidebar does not offer it
        $this->withSession($this->session('E'))->get('admin/dashboard')->assertDontSee('admin/business/stores');
        $this->withSession($this->session('M'))->get('admin/dashboard')->assertSee('admin/business/stores');
    }

    public function testEditPageShowsFixedCountryAndCurrencyAndAsksBeforeASlugChange(): void
    {
        $this->stubApi($this->switched(), [200, ['status' => 'success', 'data' => self::TYPES]]);

        $result = $this->withSession($this->session('M'))->get('admin/business');

        $result->assertOK();
        $this->assertSame(['v1/client/business/switch', 'v1/client/business-types'], array_column($this->calls, 0));
        $html = $result->getBody();
        $this->assertMatchesRegularExpression('#<select id="country_code" name="country_code"[^>]*disabled#', $html);
        $this->assertMatchesRegularExpression('#<select id="currency_code" name="currency_code"[^>]*disabled#', $html);
        $result->assertSee('name="current_business_slug" value="otter-cafe"');
        $result->assertSee('value="otter-cafe"');
        $result->assertSee('<option value="TH-10" selected>');
        $result->assertSee('The URL to your store will be changed immediately');
        $result->assertSee('"slug":"otter-cafe"');
        $result->assertDontSee('name="tax_id"');
        // the names in the UI languages, filled in; not kept in the session
        $result->assertSee('name="translation_th" class="form-control" maxlength="255" value="' . esc('ออตเตอร์คาเฟ่') . '"');
        $result->assertSee('name="translation_en-US"');
        $result->assertDontSee('name="translation_ja"');
        $this->assertArrayNotHasKey('translations', $_SESSION['business']);
    }

    public function testEditPageLeavesOutTheNamesWhenTheyCannotBeFetched(): void
    {
        $this->stubApi([500, ['status' => 'error']], [200, ['status' => 'success', 'data' => self::TYPES]]);

        $result = $this->withSession($this->session('O'))->get('admin/business');

        $result->assertOK();
        $result->assertSee('value="otter-cafe"');
        $result->assertDontSee('name="translation_th"');
    }

    public function testSaveNeverSendsCountryOrCurrencyAndFollowsANewSlug(): void
    {
        $renamed = ['business_slug' => 'otter-cafe-bkk'] + self::BUSINESS;
        $this->stubApi([200, ['status' => 'success', 'data' => ['business_slug' => 'otter-cafe-bkk'], 'message' => 'Business saved.']],
            $this->switched($renamed));
        // a form without the names (see testEditPageLeavesOutTheNamesWhenTheyCannotBeFetched)

        $result = $this->withSession($this->session('O'))->post('admin/business/save', [
            'current_business_slug' => 'otter-cafe',
            'business_name'         => 'Otter Cafe',
            'business_slug'         => 'otter-cafe-bkk',
            'country_code'          => 'SG',
            'currency_code'         => 'SGD',
            'tax_id'                => 'T1',
        ]);

        $result->assertOK();
        [$endpoint, $payload] = $this->calls[0];
        $this->assertSame('v1/client/business/edit', $endpoint);
        $this->assertSame('otter-cafe', $payload['business_slug']);
        $this->assertSame('otter-cafe-bkk', $payload['business']['business_slug']);
        foreach (['country_code', 'currency_code', 'tax_id'] as $field) {
            $this->assertArrayNotHasKey($field, $payload['business']);
        }
        $this->assertArrayNotHasKey('translations', $payload);
        $this->assertSame(['v1/client/business/switch', ['business_slug' => 'otter-cafe-bkk']], $this->calls[1]);
        $this->assertSame('otter-cafe-bkk', $_SESSION['business']['business']['business_slug']);
    }

    public function testSaveReplacesTheNamesOfTheFormsLanguagesAndKeepsTheOthers(): void
    {
        $this->stubApi($this->switched(), [422, ['status' => 'error', 'message' => 'Please correct the highlighted fields.',
            'errors' => ['translations.th' => 'Too long', 'business.business_name' => 'Taken']]]);

        $result = $this->withSession($this->session('M'))->post('admin/business/save', ['current_business_slug' => 'otter-cafe',
            'business_name' => 'Otter Cafe', 'translation_en-US' => '', 'translation_th' => 'ออตเตอร์', 'translation_ja' => 'dropped']);

        $result->assertStatus(422);
        $this->assertSame(['v1/client/business/switch', ['business_slug' => 'otter-cafe']], $this->calls[0]);
        [$endpoint, $payload] = $this->calls[1];
        $this->assertSame('v1/client/business/edit', $endpoint);
        $this->assertSame(['th' => 'ออตเตอร์', 'ja' => 'オッターカフェ', 'en-US' => ''], (array) $payload['translations']);
        $this->assertSame(['translation_th' => 'Too long', 'business_name' => 'Taken'], $this->json($result)['errors']);
    }

    public function testSaveRefusesAFormOfAnotherBusiness(): void
    {
        $result = $this->withSession($this->session('O'))->post('admin/business/save', ['current_business_slug' => 'other', 'business_name' => 'x']);
        $result->assertStatus(409);
        $this->assertSame([], $this->calls);
    }

    public function testStoresDataListsTheStoresOfTheSessionBusiness(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => self::STORES]]);

        $result = $this->withSession($this->session('M'))->post('admin/business/stores/data', ['draw' => 1, 'start' => 0, 'length' => 10,
            'search' => ['value' => 'online']]);

        $result->assertOK();
        $this->assertSame(['v1/client/stores', ['business_slug' => 'otter-cafe']], $this->calls[0]);
        $body = $this->json($result);
        $this->assertSame([2, 1], [$body['recordsTotal'], $body['recordsFiltered']]); // found by its type's label
        $this->assertSame('st0re2', $body['data'][0]['public_id']);
        $this->assertSame(base_url('admin/business/stores/edit/st0re2'), $body['data'][0]['edit_url']);
    }

    public function testStoreFormOpensAStoreWithItsNames(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => self::STORES]]);

        $result = $this->withSession($this->session('O'))->get('admin/business/stores/edit/st0re1');

        $result->assertOK();
        $result->assertSee('name="public_id" value="st0re1"');
        $result->assertSee('value="Bangkok"');
        $result->assertSee('value="กรุงเทพ"');
        $result->assertSee('data-store-address');
        $result->assertSee('"online":"O"');
    }

    public function testUnknownStoreIsNotFound(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => self::STORES]]);
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $this->withSession($this->session('O'))->get('admin/business/stores/edit/nope');
    }

    public function testCreateStoreSendsTheBusinessSlug(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => ['public_id' => 'new1'], 'message' => 'Store created.']]);

        $result = $this->withSession($this->session('M'))->post('admin/business/stores/save', ['store_name' => 'Pattaya',
            'store_type' => 'P', 'translation_th' => 'พัทยา', 'unexpected' => 'dropped']);

        $result->assertOK();
        [$endpoint, $payload] = $this->calls[0];
        $this->assertSame('v1/client/store/create', $endpoint);
        $this->assertSame('otter-cafe', $payload['business_slug']);
        $this->assertSame(['store_name' => 'Pattaya', 'store_type' => 'P'], $payload['store']);
        $this->assertSame(['en-US' => '', 'th' => 'พัทยา'], (array) $payload['translations']);
        $this->assertSame(base_url('admin/business/stores/edit/new1'), $this->json($result)['redirect']);
    }

    public function testEditStoreKeepsNamesInOtherLanguages(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => self::STORES]],
            [422, ['status' => 'error', 'message' => 'Please correct the highlighted fields.', 'errors' => ['translations.th' => 'Too long']]]);

        $result = $this->withSession($this->session('O'))->post('admin/business/stores/save', ['public_id' => 'st0re1',
            'store_name' => 'Bangkok', 'store_type' => 'P', 'translation_th' => 'กรุงเทพฯ']);

        $result->assertStatus(422);
        [$endpoint, $payload] = $this->calls[1];
        $this->assertSame('v1/client/store/edit', $endpoint);
        $this->assertSame('st0re1', $payload['public_id']);
        $this->assertSame(['th' => 'กรุงเทพฯ', 'ja' => 'バンコク', 'en-US' => ''], (array) $payload['translations']);
        $this->assertSame(['translation_th' => 'Too long'], $this->json($result)['errors']);
    }
}
