<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * Small JSON lookups for the admin pages' scripts, under admin/api/ (signed-in clients only, see AuthFilter).
 * Every answer is {status: 'success', data} or {status: 'error', message, errors}.
 */
class ApiList extends BaseController
{
    /** business_master.business_slug is VARCHAR(128) */
    private const SLUG_MAX_LENGTH = 128;

    /**
     * POST admin/api/telephone-format-e164 { country-code, telephone-number }
     * The number in E.164 (e.g. 0812345678 with TH -> +66812345678), through formatPhoneNumberStandard(),
     * plus how it reads on screen. POST, so the number does not end up in URLs and access logs.
     */
    public function telephoneFormatE164(): ResponseInterface
    {
        $country = $this->countryCode();
        $number  = trim((string) $this->request->getPost('telephone-number'));
        if ($country === null) {
            return $this->error(lang('ApiList.invalid-country'), ['country-code' => lang('ApiList.invalid-country')]);
        }
        if ($number === '') {
            return $this->error(lang('ApiList.invalid-telephone'), ['telephone-number' => lang('ApiList.invalid-telephone')]);
        }

        $e164 = formatPhoneNumberStandard($number, $country);
        if ($e164 === false) {
            return $this->error(lang('ApiList.invalid-telephone'), ['telephone-number' => lang('ApiList.invalid-telephone')]);
        }
        return $this->success(['e164' => $e164, 'formatted' => formatPhoneNumber($e164)]);
    }

    /**
     * GET|POST admin/api/subdivisions { country-code }
     * The states / provinces of the country: subdivision code => name, from listSupportedSubdivisions()
     */
    public function subdivisions(): ResponseInterface
    {
        $country = $this->countryCode();
        if ($country === null) {
            return $this->error(lang('ApiList.invalid-country'), ['country-code' => lang('ApiList.invalid-country')]);
        }
        return $this->success(listSupportedSubdivisions($country));
    }

    /**
     * GET|POST admin/api/currencies { country-code }
     * The currencies a business in the country may use: currency code => name, from listSupportedCurrencies()
     */
    public function currencies(): ResponseInterface
    {
        $country = $this->countryCode();
        if ($country === null) {
            return $this->error(lang('ApiList.invalid-country'), ['country-code' => lang('ApiList.invalid-country')]);
        }
        $currencies = [];
        foreach (listSupportedCurrencies($country) as $code) {
            $currencies[$code] = mapCurrencyCode($code);
        }
        return $this->success($currencies);
    }

    /**
     * GET|POST admin/api/generate-slug { text, strict?, max-length? }
     * A URL-safe slug of any text, kept in its own script where App::$permittedURIChars allows it, e.g.
     * "Otter Café & Bakery" -> otter-cafe-bakery, "ร้าน Otter" -> ร้าน-otter: characters outside permittedURIChars are
     * dropped, Latin letters lose their accents and go lower case, and every run of anything but letters and digits
     * becomes one hyphen. Only when nothing is left (e.g. Arabic) is the name transliterated to Latin.
     * Every slug fits BusinessMasterModel::SLUG_PATTERN (business_slug).
     * strict=1 always transliterates, keeping only a-z, 0-9 and single hyphens between them (ออตเตอร์ -> xxt-texr),
     * for anything that must be ASCII. max-length (default 128 characters) cuts it, never leaving a hyphen at either end.
     */
    public function generateSlug(): ResponseInterface
    {
        $text      = (string) $this->request->getGetPost('text');
        $strict    = in_array((string) $this->request->getGetPost('strict'), ['1', 'true'], true);
        $maxLength = (int) ($this->request->getGetPost('max-length') ?? 0);
        $maxLength = $maxLength > 0 ? min($maxLength, self::SLUG_MAX_LENGTH) : self::SLUG_MAX_LENGTH;

        $slug = self::slugify($text, $strict, $maxLength);
        if ($slug === '') {
            return $this->error(lang('ApiList.invalid-slug-text'), ['text' => lang('ApiList.invalid-slug-text')]);
        }
        return $this->success(['slug' => $slug]);
    }

    /**
     * @see generateSlug()
     */
    public static function slugify(string $text, bool $strict = false, int $maxLength = self::SLUG_MAX_LENGTH): string
    {
        helper('text');
        if (class_exists(\Normalizer::class)) {
            $text = (string) \Normalizer::normalize($text, \Normalizer::FORM_C); // é as one character, not e + a combining accent
        }
        // the name as it is, in its own script, when any of it may be in a URL; otherwise (or strict) transliterated
        $slug = $strict ? '' : self::slugWords($text);
        if ($slug === '') {
            if (function_exists('transliterator_transliterate')) {
                $text = (string) transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
            } // without intl, only accented Latin letters can be turned into ASCII (convert_accented_characters below)
            $slug = self::slugWords($text);
            if ($strict) {
                $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $slug), '-');
            }
        }
        return trim(mb_substr($slug, 0, $maxLength), '-');
    }

    /**
     * 1. drop every character the router refuses ('/\A[<permittedURIChars>]+\z/iu'),
     * 2. Latin letters without accents, in lower case,
     * 3. anything left that is not a letter, a mark (Thai vowels and tones) or a digit -- spaces, ~ % . : _ -- is a hyphen.
     * '' when no letter or digit is left.
     */
    private static function slugWords(string $text): string
    {
        $text      = (string) preg_replace('/\s+/u', ' ', $text);
        $permitted = config('App')->permittedURIChars;
        if ($permitted !== '') {
            $text = (string) preg_replace('/[^' . $permitted . ']+/iu', '', $text);
        }
        $text = mb_strtolower(convert_accented_characters($text));
        $text = (string) preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '-', $text);
        return trim($text, '-');
    }

    /**
     * The country-code parameter (query string or body) in capitals; null when it is not a supported country,
     * as the list helpers return every country's list for an empty code
     */
    private function countryCode(): ?string
    {
        $country = strtoupper(trim((string) $this->request->getGetPost('country-code')));
        return isset(listSupportedCountries()[$country]) ? $country : null;
    }

    private function success(array $data): ResponseInterface
    {
        return $this->response->setJSON([
            'status'    => 'success',
            'data'      => (object) $data, // {} rather than [] when the list is empty
            'csrf_hash' => csrf_hash(),
        ]);
    }

    private function error(string $message, array $errors): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setJSON([
            'status'    => 'error',
            'message'   => $message,
            'errors'    => $errors,
            'csrf_hash' => csrf_hash(),
        ]);
    }
}
