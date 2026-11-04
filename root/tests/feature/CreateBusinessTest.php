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
        $result->assertSee('value="client@example.com"');
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
