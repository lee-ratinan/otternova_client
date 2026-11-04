<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * admin/api/*: the JSON lookups of the admin pages' scripts
 *
 * @internal
 */
final class ApiListTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private function signedIn(?array $business = null): self
    {
        return $this->withSession(['access_token' => 'test-token', 'expires_at' => time() + 3600, 'user' => ['id' => 1], 'business' => $business]);
    }

    private function json($result): array
    {
        return json_decode($result->getJSON(), true);
    }

    public function testTelephoneIsFormattedToE164(): void
    {
        // a client still without a business reaches it too, as the create-business form needs it
        $result = $this->signedIn()->post('admin/api/telephone-format-e164', ['country-code' => 'th', 'telephone-number' => '081-234-5678']);

        $result->assertOK();
        $body = $this->json($result);
        $this->assertSame('success', $body['status']);
        $this->assertSame('+66812345678', $body['data']['e164']);
        $this->assertSame('+66 81 234 5678', $body['data']['formatted']);
    }

    public function testInvalidTelephoneOrCountryIsRejected(): void
    {
        $result = $this->signedIn()->post('admin/api/telephone-format-e164', ['country-code' => 'TH', 'telephone-number' => '123']);
        $result->assertStatus(422);
        $this->assertSame(['telephone-number'], array_keys($this->json($result)['errors']));

        $result = $this->signedIn()->post('admin/api/telephone-format-e164', ['country-code' => 'US', 'telephone-number' => '2025550123']);
        $result->assertStatus(422);
        $this->assertSame(['country-code'], array_keys($this->json($result)['errors']));
    }

    public function testSubdivisionsOfTheCountry(): void
    {
        $result = $this->signedIn()->get('admin/api/subdivisions?country-code=SG');

        $result->assertOK();
        $this->assertSame(listSupportedSubdivisions('SG'), $this->json($result)['data']);
        $this->assertArrayHasKey('TH-10', $this->json($this->signedIn()->get('admin/api/subdivisions?country-code=TH'))['data']);
    }

    public function testCurrenciesOfTheCountry(): void
    {
        $result = $this->signedIn()->get('admin/api/currencies?country-code=JP');

        $result->assertOK();
        $this->assertSame(['JPY' => mapCurrencyCode('JPY'), 'USD' => mapCurrencyCode('USD')], $this->json($result)['data']);
    }

    public function testListsNeedACountry(): void
    {
        // an empty code would otherwise list every country
        $this->signedIn()->get('admin/api/currencies')->assertStatus(422);
        $this->signedIn()->get('admin/api/subdivisions?country-code=XX')->assertStatus(422);
    }

    public function testSignInIsRequired(): void
    {
        $this->post('admin/api/telephone-format-e164', ['country-code' => 'TH', 'telephone-number' => '0812345678'])->assertStatus(401);
        $this->get('admin/api/currencies?country-code=TH')->assertRedirectTo(base_url());
    }
}
