<?php

use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\App;

/**
 * A client active in no business is held at admin/create-business until they create one through
 * v1/client/business/create (stubbed here).
 *
 * @internal
 */
final class CreateBusinessTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const USER = ['id' => 1, 'uid' => 'test-uid', 'email_address' => 'client@example.com', 'full_name' => 'Test Client',
        'avatar_path' => null, 'display_name' => null, 'preferred_language' => 'en-US'];
    private const TYPES = ['rows' => [
        ['public_id' => 'aB3d', 'type_code' => 'cafe', 'type_status' => 'A', 'translations' => ['en-US' => 'Café', 'th' => 'คาเฟ่']],
        ['public_id' => 'xY9z', 'type_code' => 'bakery', 'type_status' => 'A', 'translations' => []],
    ]];

    /** @var list<array{string, array}> endpoints and payloads sent to the API */
    private array $calls = [];

    protected function tearDown(): void
    {
        Services::reset();
        parent::tearDown();
    }

    /**
     * Answer the next API calls in order with [http status, body].
     */
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

    private function session(?array $business): array
    {
        return ['access_token' => 'test-token', 'expires_at' => time() + 3600, 'user' => self::USER, 'business' => $business];
    }

    private function json($result): array
    {
        return json_decode($result->getJSON(), true);
    }

    public function testClientWithoutBusinessIsSentToCreateBusiness(): void
    {
        foreach (['admin/dashboard', 'admin/profile', 'admin/my-businesses'] as $page) {
            $this->withSession($this->session(null))->get($page)->assertRedirectTo(base_url('admin/create-business'));
        }
    }

    public function testClientWithBusinessReachesDashboard(): void
    {
        $this->withSession($this->session(['business' => ['business_slug' => 'otter-cafe-sg']]))->get('admin/dashboard')->assertOK();
    }

    public function testFormShowsActiveTypesAndPrefillsContact(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => self::TYPES]]);

        $result = $this->withSession($this->session(null))->get('admin/create-business');

        $result->assertOK();
        $this->assertSame('v1/client/business-types', $this->calls[0][0]);
        $result->assertSee('create-business-form');
        $result->assertSee('value="aB3d"');
        $result->assertSee('Café');
        $result->assertSee('bakery'); // no name in any language: the code
        $result->assertSee('name="business_slug"');
        $result->assertSee('name="translation_th"');
        // the audience, the general public to start with
        foreach (['lgbt_audience', 'naturist_audience', 'age_restricted'] as $name) {
            $result->assertSee('name="' . $name . '"');
        }
        $result->assertSee('<option value="GEN" selected');
        $result->assertSee('<option value="N" selected');
        $result->assertSee('LGBT-friendly');
        // no tips or service charge to start with
        foreach (['tip_policy', 'tip_value', 'tip_unit', 'tip_value_kind', 'service_charge_value', 'service_charge_unit'] as $name) {
            $result->assertSee('name="' . $name . '"');
        }
        $result->assertSee('<option value="D" selected');
        $result->assertSee('Required at checkout');
        $this->assertMatchesRegularExpression('#name="service_charge_value"[^>]*value="0.00"#', $result->getBody());
        $result->assertSee('value="client@example.com"');

        // country and currency right under the slug
        $html = $result->getBody();
        $at   = static fn (string $name) => strpos($html, 'data-fb-row="' . $name . '"');
        $this->assertTrue($at('business_slug') < $at('country_code') && $at('country_code') < $at('currency_code')
            && $at('currency_code') < $at('business_description'));

        // currency and subdivision start without options; the page's script fills them for the country
        $this->assertMatchesRegularExpression('#<select id="currency_code"[^>]*><option value="" selected>[^<]*</option></select>#', $html);
        $this->assertMatchesRegularExpression('#<select id="contact_subdivision_code"[^>]*><option value="" selected>[^<]*</option></select>#', $html);
        $result->assertSee('"currencies":"' . base_url('admin/api/currencies') . '"');
        $result->assertSee('form.elements.contact_subdivision_code');
        $result->assertSee('"slug":"' . base_url('admin/api/generate-slug') . '"');

        // the phone: country dropdown and number, neither submitted, and the hidden E.164 input
        $result->assertSee('id="contact_phone_number__country"');
        $result->assertSee('<option value="TH">TH +66</option>');
        $result->assertSee('type="hidden" id="contact_phone_number__e164" name="contact_phone_number"');
        $this->assertSame(1, substr_count($html, 'name="contact_phone_number"'));
        $result->assertSee('"telephoneFormat":"' . base_url('admin/api/telephone-format-e164') . '"');
    }

    public function testSavedTelephoneIsSplitForEditing(): void
    {
        helper('form');
        $html = fb_render(['form_id' => 'f', 'mode' => 'view', 'values' => ['phone' => '+66812345678'],
            'fields' => [['name' => 'phone', 'type' => 'tel', 'label' => 'Phone']]]);

        $this->assertStringContainsString('<option value="TH" selected>TH +66</option>', $html);
        $this->assertStringContainsString('value="0812345678"', $html);
        $this->assertStringContainsString('name="phone" value="&#x2B;66812345678"', $html);
        $this->assertStringContainsString('+66 81 234 5678', $html); // view pane
    }

    public function testCreateSendsTheFieldsAndSetsTheSessionBusiness(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => ['business_slug' => 'otter-cafe-sg'], 'message' => 'Business created.']]);

        $result = $this->withSession($this->session(null))->post('admin/create-business', [
            'business_type_public_id' => 'aB3d',
            'business_name'           => 'Otter Cafe',
            'business_slug'           => 'otter-cafe-sg',
            'contact_email_address'   => 'hello@otter.cafe',
            'country_code'            => 'SG',
            'currency_code'           => 'SGD',
            'tax_id'                  => 'T123',
            'lgbt_audience'           => 'FNDLY',
            'naturist_audience'       => 'GEN',
            'age_restricted'          => 'Y',
            'tip_policy'              => 'O',
            'tip_value'               => '15',
            'tip_unit'                => 'P',
            'tip_value_kind'          => 'S',
            'service_charge_value'    => '0',
            'service_charge_unit'     => 'P',
            'translation_th'          => 'ออตเตอร์คาเฟ่',
            'translation_en-US'       => '',
            'unexpected'              => 'dropped',
        ]);

        $result->assertOK();
        $this->assertSame(base_url('admin/dashboard'), $this->json($result)['redirect']);
        [$endpoint, $payload] = $this->calls[0];
        $this->assertSame('v1/client/business/create', $endpoint);
        $this->assertArrayNotHasKey('unexpected', $payload['business']);
        $this->assertSame('aB3d', $payload['business']['business_type_public_id']);
        $this->assertSame('T123', $payload['business']['tax_id']);
        $this->assertSame(['FNDLY', 'GEN', 'Y'], [$payload['business']['lgbt_audience'], $payload['business']['naturist_audience'], $payload['business']['age_restricted']]);
        $this->assertSame(['O', '15', 'S', '0'], [$payload['business']['tip_policy'], $payload['business']['tip_value'],
            $payload['business']['tip_value_kind'], $payload['business']['service_charge_value']]);
        $this->assertSame(['en-US' => '', 'th' => 'ออตเตอร์คาเฟ่'], (array) $payload['translations']);

        $business = $_SESSION['business'];
        $this->assertSame(['O', 'A', 'Y'], [$business['client_role'], $business['client_status'], $business['is_default_business']]);
        $this->assertSame('otter-cafe-sg', $business['business']['business_slug']);
        $this->assertArrayNotHasKey('tax_id', $business['business']);
    }

    public function testApiErrorsComeBackOnTheFormFields(): void
    {
        $this->stubApi([422, ['status' => 'error', 'message' => 'Please correct the highlighted fields.', 'errors' => [
            'business_slug'  => 'The Business URL Name field must contain a unique value.',
            'translations.th' => 'The name can be at most 255 characters.',
        ]]]);

        $result = $this->withSession($this->session(null))->post('admin/create-business', ['business_name' => 'Otter Cafe']);

        $result->assertStatus(422);
        $this->assertSame(['business_slug', 'translation_th'], array_keys($this->json($result)['errors']));
        $this->assertNull($_SESSION['business']);
    }
}
