<?php

use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\App;

/**
 * Saving a session (admin/sessions/save) through v1/client/session/create and session/edit (stubbed here): when
 * booking closes is left to the API
 *
 * @internal
 */
final class SessionsSaveTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const USER = ['id' => 1, 'uid' => 'test-uid', 'email_address' => 'client@example.com', 'full_name' => 'Test Client',
        'avatar_path' => null, 'display_name' => null, 'preferred_language' => 'en-US'];

    /** @var list<array{string, array}> endpoints and payloads sent to the API */
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        $curl = $this->createMock(CURLRequest::class);
        $curl->method('post')->willReturnCallback(function (string $url, array $options) {
            $this->calls[] = [substr($url, strlen((string) getenv('LINK_INTERNAL_API'))), $options['json'] ?? []];
            $body = ['status' => 'success', 'data' => ['session' => ['public_id' => 'sess01', 'translations' => []], 'languages' => []]];
            return (new Response(new App()))->setStatusCode(200)->setBody(json_encode($body));
        });
        Services::injectMock('curlrequest', $curl);
    }

    protected function tearDown(): void
    {
        Services::reset();
        parent::tearDown();
    }

    public function testAnEmptyBookingCloseIsLeftToTheBookingWindowOnlyForANewSession(): void
    {
        $session = ['access_token' => 'test-token', 'expires_at' => time() + 3600, 'user' => self::USER,
            'business' => ['client_role' => 'O', 'client_status' => 'A', 'is_default_business' => 'Y', 'business' => ['business_slug' => 'otter-school']]];
        $form = ['session_name' => 'Course', 'capacity' => '10', 'booking_closes_at' => '', 'session_status' => 'D', 'slots_json' => '[]'];

        $this->withSession($session)->post('admin/sessions/save', $form + ['variant_public_id' => 'var01', 'store_public_id' => 'st01'])->assertStatus(200);
        $this->withSession($session)->post('admin/sessions/save', $form + ['public_id' => 'sess01'])->assertStatus(200);

        $sent = array_values(array_filter($this->calls, static fn (array $call) => in_array($call[0], ['v1/client/session/create', 'v1/client/session/edit'], true)));
        $this->assertCount(2, $sent);
        $this->assertArrayNotHasKey('booking_closes_at', $sent[0][1]['session']); // the API sets it from the notice
        $this->assertArrayHasKey('booking_closes_at', $sent[1][1]['session']);
        $this->assertNull($sent[1][1]['session']['booking_closes_at']); // until the first slot starts
    }
}
