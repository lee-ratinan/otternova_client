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
        'contact_email_address' => 'hello@otter.cafe', 'contact_subdivision_code' => 'TH-10', 'country_code' => 'TH', 'currency_code' => 'THB',
        'lgbt_audience' => 'GAY', 'naturist_audience' => 'OPT', 'age_restricted' => 'Y'];
    private const TYPES = ['rows' => [['public_id' => 'aB3d', 'type_code' => 'cafe', 'translations' => ['en-US' => 'Café']]]];
    private const STORES = ['stores' => [
        ['public_id' => 'st0re1', 'store_name' => 'Bangkok', 'store_type' => 'P', 'store_phone_number' => '+66812345678',
            'store_address_line_1' => '1 Rama Rd', 'store_address_line_2' => null, 'store_city' => 'Pathum Wan',
            'store_subdivision_code' => 'TH-10', 'store_postal_code' => '10330', 'translations' => [
                'th' => ['store_name' => 'กรุงเทพ', 'store_address_line_1' => '1 ถนนพระราม', 'store_address_line_2' => null, 'store_city' => 'ปทุมวัน'],
                'ja' => ['store_name' => 'バンコク', 'store_address_line_1' => null, 'store_address_line_2' => null, 'store_city' => null]]],
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
            $endpoint = substr($url, strlen((string) getenv('LINK_INTERNAL_API')));
            // the languages of the web pages, which the forms ask for (websiteLanguages()), answered here and not recorded
            if ('v1/client/website' === $endpoint) {
                return (new Response(new App()))->setStatusCode(200)->setBody(json_encode(['status' => 'success',
                    'data' => ['languages' => [['language_code' => 'en-US'], ['language_code' => 'th']]]]));
            }
            $this->calls[] = [$endpoint, $options['json'] ?? []];
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

    private function switched(array $business = self::BUSINESS, array $names = ['th' => 'ออตเตอร์คาเฟ่', 'ja' => 'オッターカフェ'], array $taxId = ['tax_id' => '0105561234567']): array
    {
        return [200, ['status' => 'success', 'data' => ['business' => ['client_role' => 'O', 'client_status' => 'A',
            'is_default_business' => 'Y', 'business' => $business], 'translations' => $names] + $taxId]];
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
        $result->assertSee('<option value="GAY" selected>');
        $result->assertSee('<option value="OPT" selected>');
        $result->assertSee('<option value="Y" selected>');
        $result->assertSee('The URL to your store will be changed immediately');
        $result->assertSee('"slug":"otter-cafe"');
        // the tax ID, under the postal code; not kept in the session
        $this->assertMatchesRegularExpression('#name="contact_postal_code".*name="tax_id"[^>]*value="0105561234567"#s', $html);
        $this->assertArrayNotHasKey('tax_id', $_SESSION['business']['business']);
        // the names in the UI languages, filled in; not kept in the session
        $result->assertSee('name="translation_th" class="form-control" maxlength="255" value="' . esc('ออตเตอร์คาเฟ่') . '"');
        $result->assertSee('name="translation_en-US"');
        $result->assertDontSee('name="translation_ja"');
        $this->assertArrayNotHasKey('translations', $_SESSION['business']);
    }

    public function testEditPageShowsTheTipsAndServiceCharge(): void
    {
        $charges = ['tip_policy' => 'OFF', 'tip_value' => '5.00', 'tip_unit' => 'F', 'tip_value_kind' => 'S',
            'service_charge_value' => '12.50', 'service_charge_unit' => 'P'];
        $this->stubApi($this->switched($charges + self::BUSINESS), [200, ['status' => 'success', 'data' => self::TYPES]]);

        $result = $this->withSession($this->session('O'))->get('admin/business');

        $result->assertOK();
        $html = $result->getBody();
        $result->assertSee('Tips and Service Charge');
        $result->assertSee('<option value="OFF" selected>');
        $result->assertSee('<option value="F" selected>');
        $this->assertMatchesRegularExpression('#name="tip_value"[^>]*value="5.00"#', $html);
        $this->assertMatchesRegularExpression('#name="service_charge_value"[^>]*value="12.50"#', $html);
        $result->assertSee('Added to each service in an order, not to products');
    }

    public function testEditPageOfAnOlderSessionBusinessShowsNoTipsOrServiceCharge(): void
    {
        // a business as the API gave it before it had tips and service charges
        $this->stubApi([500, ['status' => 'error']], [200, ['status' => 'success', 'data' => self::TYPES]]);

        $result = $this->withSession($this->session('O'))->get('admin/business');

        $result->assertOK();
        $result->assertSee('<option value="D" selected>');
        $this->assertMatchesRegularExpression('#name="tip_value"[^>]*value="0.00"#', $result->getBody());
    }

    public function testEditPageLeavesOutTheNamesWhenTheyCannotBeFetched(): void
    {
        $this->stubApi([500, ['status' => 'error']], [200, ['status' => 'success', 'data' => self::TYPES]]);

        $result = $this->withSession($this->session('O'))->get('admin/business');

        $result->assertOK();
        $result->assertSee('value="otter-cafe"');
        $result->assertDontSee('name="translation_th"');
        $result->assertDontSee('name="tax_id"');
    }

    public function testEditPageLeavesOutTheTaxIdWhenTheApiDoesNotGiveIt(): void
    {
        $this->stubApi($this->switched(self::BUSINESS, [], []), [200, ['status' => 'success', 'data' => self::TYPES]]);

        $result = $this->withSession($this->session('O'))->get('admin/business');

        $result->assertOK();
        $result->assertSee('name="contact_postal_code"');
        $result->assertDontSee('name="tax_id"');
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
            'lgbt_audience'         => 'LES',
            'naturist_audience'     => 'NAT',
            'age_restricted'        => 'N',
            'tip_policy'            => 'M',
            'tip_value'             => '10',
            'tip_unit'              => 'P',
            'tip_value_kind'        => 'M',
            'service_charge_value'  => '2.5',
            'service_charge_unit'   => 'F',
        ]);

        $result->assertOK();
        [$endpoint, $payload] = $this->calls[0];
        $this->assertSame('v1/client/business/edit', $endpoint);
        $this->assertSame('otter-cafe', $payload['business_slug']);
        $this->assertSame('otter-cafe-bkk', $payload['business']['business_slug']);
        foreach (['country_code', 'currency_code'] as $field) {
            $this->assertArrayNotHasKey($field, $payload['business']);
        }
        $this->assertSame('T1', $payload['business']['tax_id']);
        $this->assertArrayNotHasKey('translations', $payload);
        $this->assertSame(['LES', 'NAT', 'N'], [$payload['business']['lgbt_audience'], $payload['business']['naturist_audience'], $payload['business']['age_restricted']]);
        $this->assertSame(['M', '10', 'P', 'M', '2.5', 'F'], [$payload['business']['tip_policy'], $payload['business']['tip_value'],
            $payload['business']['tip_unit'], $payload['business']['tip_value_kind'], $payload['business']['service_charge_value'],
            $payload['business']['service_charge_unit']]);
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

    public function testStoreFormOpensAStoreWithItsNamesAndAddresses(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => self::STORES]]);

        $result = $this->withSession($this->session('O'))->get('admin/business/stores/edit/st0re1');

        $result->assertOK();
        $result->assertSee('name="public_id" value="st0re1"');
        $result->assertSee('value="Bangkok"');
        $result->assertSee('name="translation_th" class="form-control" maxlength="255" value="' . esc('กรุงเทพ') . '"');
        $result->assertSee('name="translation_address_line_1_th" class="form-control" maxlength="255" value="' . esc('1 ถนนพระราม') . '"');
        $result->assertSee('name="translation_city_th" class="form-control" maxlength="128" value="' . esc('ปทุมวัน') . '"');
        $result->assertSee('name="translation_address_line_2_en-US"');
        $result->assertDontSee('name="translation_ja"');
        $result->assertSee('data-store-address');
        $result->assertSee('"online":"O"');
        $result->assertSee('"translation_city_th"'); // hides with the address for an online store
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
            'store_type' => 'P', 'translation_th' => 'พัทยา', 'translation_city_th' => ' พัทยา ', 'unexpected' => 'dropped']);

        $result->assertOK();
        [$endpoint, $payload] = $this->calls[0];
        $this->assertSame('v1/client/store/create', $endpoint);
        $this->assertSame('otter-cafe', $payload['business_slug']);
        $this->assertSame(['store_name' => 'Pattaya', 'store_type' => 'P'], $payload['store']);
        $empty = ['store_name' => '', 'store_address_line_1' => '', 'store_address_line_2' => '', 'store_city' => ''];
        $this->assertSame(['en-US' => $empty, 'th' => array_merge($empty, ['store_name' => 'พัทยา', 'store_city' => ' พัทยา '])], (array) $payload['translations']);
        $this->assertSame(base_url('admin/business/stores/edit/new1'), $this->json($result)['redirect']);
    }

    public function testEditStoreKeepsNamesInOtherLanguages(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => self::STORES]],
            [422, ['status' => 'error', 'message' => 'Please correct the highlighted fields.', 'errors' => ['translations.th.store_city' => 'Too long', 'translations.th.store_name' => 'Needed', 'store.store_name' => 'Taken']]]);

        $result = $this->withSession($this->session('O'))->post('admin/business/stores/save', ['public_id' => 'st0re1',
            'store_name' => 'Bangkok', 'store_type' => 'P', 'translation_th' => 'กรุงเทพฯ', 'translation_address_line_1_th' => '1 ถนนพระราม 1']);

        $result->assertStatus(422);
        [$endpoint, $payload] = $this->calls[1];
        $this->assertSame('v1/client/store/edit', $endpoint);
        $this->assertSame('st0re1', $payload['public_id']);
        $empty = ['store_name' => '', 'store_address_line_1' => '', 'store_address_line_2' => '', 'store_city' => ''];
        $this->assertSame([
            'th'    => array_merge($empty, ['store_name' => 'กรุงเทพฯ', 'store_address_line_1' => '1 ถนนพระราม 1']),
            'ja'    => self::STORES['stores'][0]['translations']['ja'], // not a language of the web pages: kept as it is
            'en-US' => $empty,
        ], (array) $payload['translations']);
        $this->assertSame(['translation_city_th' => 'Too long', 'translation_th' => 'Needed', 'store_name' => 'Taken'], $this->json($result)['errors']);
    }
}
