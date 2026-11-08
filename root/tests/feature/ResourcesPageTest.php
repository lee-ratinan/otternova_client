<?php

use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\App;

/**
 * The resources of the session business (admin/business/resource-types/*, admin/business/resources/*) and what a
 * service needs (admin/artifacts/resources/save): its owners and managers only, through v1/client/resource*
 * and v1/client/artifact/resources/save (stubbed here).
 *
 * @internal
 */
final class ResourcesPageTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const USER     = ['id' => 1, 'uid' => 'test-uid', 'email_address' => 'client@example.com', 'full_name' => 'Test Client',
        'avatar_path' => null, 'display_name' => null, 'preferred_language' => 'en-US'];
    private const BUSINESS = ['business_slug' => 'otter-spa', 'business_name' => 'Otter Spa', 'country_code' => 'TH', 'currency_code' => 'THB'];
    private const STORES   = [200, ['status' => 'success', 'data' => ['stores' => [
        ['public_id' => 'st0re1', 'store_name' => 'Bangkok', 'store_type' => 'P', 'translations' => []],
        ['public_id' => 'st0re2', 'store_name' => 'Online Store', 'store_type' => 'O', 'translations' => []],
    ]]]];
    private const TYPES    = [200, ['status' => 'success', 'data' => ['resource_types' => [
        ['public_id' => 'type01', 'type_name' => 'Massage Room', 'type_description' => null, 'buffer_minutes' => 15, 'type_status' => 'A',
            'resource_count' => 3, 'active_resource_count' => 2, 'service_count' => 1, 'translations' => []],
        ['public_id' => 'type02', 'type_name' => 'Sauna', 'type_description' => null, 'buffer_minutes' => 0, 'type_status' => 'I',
            'resource_count' => 0, 'active_resource_count' => 0, 'service_count' => 0, 'translations' => []],
    ]]]];

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

    private function session(string $role): array
    {
        return ['access_token' => 'test-token', 'expires_at' => time() + 3600, 'user' => self::USER,
            'business' => ['client_role' => $role, 'client_status' => 'A', 'is_default_business' => 'Y', 'business' => self::BUSINESS]];
    }

    private function json($result): array
    {
        return json_decode($result->getJSON(), true);
    }

    public function testEmployeesCannotManageResources(): void
    {
        foreach (['admin/business/resource-types', 'admin/business/resource-types/create', 'admin/business/resources', 'admin/business/resources/create'] as $page) {
            $this->withSession($this->session('E'))->get($page)->assertRedirectTo(base_url('admin/dashboard'));
        }
        foreach (['admin/business/resource-types/save', 'admin/business/resources/save', 'admin/artifacts/resources/save'] as $action) {
            $this->withSession($this->session('E'))->post($action, ['public_id' => 'x'])->assertStatus(403);
        }
        $this->assertSame([], $this->calls);

        // and the sidebar offers it to owners and managers only
        $this->withSession($this->session('E'))->get('admin/dashboard')->assertDontSee('admin/business/resource-types');
        $this->withSession($this->session('M'))->get('admin/dashboard')->assertSee('admin/business/resource-types');
        $this->withSession($this->session('O'))->get('admin/dashboard')->assertSee('admin/business/resources');
    }

    public function testNewResourceOffersPhysicalStoresAndActiveTypesOnly(): void
    {
        $this->stubApi(self::STORES, self::TYPES);
        $result = $this->withSession($this->session('M'))->get('admin/business/resources/create?type=type01');
        $result->assertOK();
        $result->assertSee('Bangkok');
        $result->assertDontSee('Online Store');
        $result->assertSee('Massage Room');
        $result->assertDontSee('Sauna');
    }

    public function testEditingAResourceNeverSendsItsStoreOrType(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => ['public_id' => 'res001']]]);
        $this->withSession($this->session('O'))->post('admin/business/resources/save', ['public_id' => 'res001', 'resource_name' => '102',
            'resource_status' => 'A', 'sort_order' => '1', 'store_public_id' => 'st0re2', 'type_public_id' => 'type02'])->assertOK();
        $this->assertSame(['v1/client/resource/edit', ['public_id' => 'res001', 'resource' => ['resource_name' => '102', 'resource_status' => 'A', 'sort_order' => '1']]], $this->calls[0]);
    }

    public function testServiceNeedsSendOnlyTheTickedTypes(): void
    {
        $this->stubApi([200, ['status' => 'success', 'message' => 'Saved.', 'data' => ['public_id' => 'svc001']]]);
        $result = $this->withSession($this->session('M'))->post('admin/artifacts/resources/save', ['artifact_public_id' => 'svc001', 'needs' => [
            'type01' => ['needed' => '1', 'quantity' => '2'],
            'type02' => ['quantity' => '1'],
        ]]);
        $result->assertOK();
        $this->assertSame(['v1/client/artifact/resources/save', ['public_id' => 'svc001', 'resources' => [['public_id' => 'type01', 'quantity' => '2']]]], $this->calls[0]);
        $this->assertSame(base_url('admin/artifacts/edit/svc001'), $this->json($result)['redirect']);
    }

    public function testServiceNeedsErrorsAreToldInTheMessage(): void
    {
        $this->stubApi([422, ['status' => 'error', 'message' => 'Please correct the highlighted fields.',
            'errors' => ['resources.0.quantity' => 'Please enter a whole number from 1 to 255.']]]);
        $result = $this->withSession($this->session('M'))->post('admin/artifacts/resources/save', ['artifact_public_id' => 'svc001',
            'needs' => ['type01' => ['needed' => '1', 'quantity' => '0']]]);
        $result->assertStatus(422);
        $this->assertSame('Please enter a whole number from 1 to 255.', $this->json($result)['message']);
    }
}
