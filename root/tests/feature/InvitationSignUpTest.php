<?php

use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\App;

/**
 * create-account?invitation=: a business owner's invitation link opens the sign-up form with the invited address,
 * and the sign-up carries the invitation to v1/create-client-account (stubbed here)
 *
 * @internal
 */
final class InvitationSignUpTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const TOKEN = 'abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789';

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

    public function testTheInvitationFillsInTheAddress(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => ['email_address' => 'new@example.com', 'business_name' => 'Otter Cafe', 'client_role' => 'M']]]);

        $result = $this->get('create-account?invitation=' . self::TOKEN);

        $result->assertOK();
        $this->assertSame([['v1/client-invitation', ['token' => self::TOKEN]]], $this->calls);
        $result->assertSee('You have been invited to work at Otter Cafe as Manager.');
        $result->assertSee('name="invitation" value="' . self::TOKEN . '"');
        $this->assertMatchesRegularExpression('#name="email"[^>]*value="new@example.com" readonly#', $result->getBody());
    }

    public function testAnExpiredInvitationSaysSoAndLeavesTheFormPlain(): void
    {
        $this->stubApi([410, ['status' => 'error', 'message' => 'This invitation link is invalid or has expired.']]);

        $result = $this->get('create-account?invitation=' . self::TOKEN);

        $result->assertOK();
        $result->assertSee('This invitation link is invalid or has expired.');
        $result->assertDontSee('name="invitation"');
        $result->assertDontSee('readonly');
    }

    public function testTheSignUpCarriesTheInvitation(): void
    {
        $this->stubApi([200, ['status' => 'success', 'message' => 'Thank you!']], [200, ['status' => 'success', 'message' => 'Thank you!']]);
        $form = ['full_name' => 'New Person', 'email' => 'new@example.com', 'password' => 'otters hold hands 2', 'terms' => '1'];

        $this->post('create-account', $form + ['invitation' => self::TOKEN])->assertOK();
        $this->post('create-account', $form)->assertOK();

        $this->assertSame(self::TOKEN, $this->calls[0][1]['invitation']);
        $this->assertArrayNotHasKey('invitation', $this->calls[1][1]);
    }
}
