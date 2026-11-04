<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * Small JSON lookups for the admin pages' scripts, under admin/api/ (signed-in clients only, see AuthFilter).
 * Every answer is {status: 'success', data} or {status: 'error', message, errors}.
 */
class ApiList extends BaseController
{
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
