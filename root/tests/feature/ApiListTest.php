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

    public function testSlugIsMadeOfPermittedCharacters(): void
    {
        $this->assertSame('otter-cafe', $this->json($this->signedIn()->get('admin/api/generate-slug?text=Otter+Cafe'))['data']['slug']);

        $slug = fn (array $post) => $this->json($this->signedIn()->post('admin/api/generate-slug', $post))['data']['slug'];

        $this->assertSame('otter-cafe-bakery', $slug(['text' => '  Otter Café & Bakery!! ']));
        // the URI characters App::$permittedURIChars allows stay, except spaces and %
        $this->assertSame('otter-co._ltd-100', $slug(['text' => 'Otter Co._Ltd 100%']));
        // business_slug takes only a-z, 0-9 and single hyphens
        $this->assertSame('otter-co-ltd', $slug(['text' => 'Otter Co._Ltd.', 'strict' => '1']));
        // other scripts become Latin letters
        $this->assertSame('khafe', $slug(['text' => 'คาเฟ่', 'strict' => '1']));
        $this->assertSame('otter', $slug(['text' => 'Otter Cafe', 'max-length' => '6']));

        $result = $this->signedIn()->post('admin/api/generate-slug', ['text' => '!!! ---']);
        $result->assertStatus(422);
        $this->assertSame(['text'], array_keys($this->json($result)['errors']));
    }

    public function testSlugCharactersAreAllAcceptedByTheRouter(): void
    {
        $permitted = config('App')->permittedURIChars;
        foreach (['Ünïcödé — test', '東京 カフェ', 'a~b:c.d_e', '%%%x%%%', str_repeat('long name ', 30)] as $text) {
            $slug = \App\Controllers\ApiList::slugify($text);
            $this->assertMatchesRegularExpression('/\A[' . $permitted . ']+\z/iu', $slug, $text);
            $this->assertDoesNotMatchRegularExpression('/[\s%]/', $slug);
            $this->assertLessThanOrEqual(128, strlen($slug));
        }
    }

    public function testSignInIsRequired(): void
    {
        $this->post('admin/api/telephone-format-e164', ['country-code' => 'TH', 'telephone-number' => '0812345678'])->assertStatus(401);
        $this->get('admin/api/currencies?country-code=TH')->assertRedirectTo(base_url());
    }
}
