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
     * A URL-safe slug of any text, e.g. "Otter Café & Bakery" -> otter-cafe-bakery: other scripts are transliterated to
     * Latin (ออตเตอร์ -> xxt-texr), every character outside App::$permittedURIChars becomes a hyphen, and so do spaces
     * and % (allowed in a URI, but not as they are). strict=1 keeps only a-z, 0-9 and single hyphens between them,
     * as business_slug takes it. max-length (default 128) cuts it, never leaving a separator at either end.
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
        if (function_exists('transliterator_transliterate')) {
            $text = (string) transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
        } else {
            helper('text'); // without intl, only accented Latin letters can be turned into ASCII
            $text = mb_strtolower(convert_accented_characters($text));
        }
        // what the router accepts: '/\A[<permittedURIChars>]+\z/iu'; anything else, spaces and % are separators
        $permitted = config('App')->permittedURIChars;
        if ($permitted !== '') {
            $text = (string) preg_replace('/[^' . $permitted . ']+/iu', '-', $text);
        }
        $text = (string) preg_replace($strict ? '/[^a-z0-9]+/' : '/[\s%]+/u', '-', $text);
        $text = (string) preg_replace('/-{2,}/', '-', $text);

        $trim = $strict ? '-' : '-._~:';
        return trim(substr(trim($text, $trim), 0, $maxLength), $trim);
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
