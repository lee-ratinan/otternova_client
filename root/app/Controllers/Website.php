<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use CURLFile;
use RuntimeException;

/**
 * How the session business's web pages look: the languages they are in, with each language's fonts, meta tags and
 * main-page text, the business name in each language (beside the languages, as it can only be named in them once they
 * are set), the theme colours, the logo, and the products and services the main page features. Its owners and
 * managers only (the routes' manager filter, and v1/client/website* in the API). The business is always the session's;
 * the page never says which.
 */
class Website extends BaseController
{
    /** The parts of the settings the page saves together */
    private const PARTS = ['colors', 'languages', 'featured'];

    /**
     * The page: every setting at once, edited in place by scripts/website and saved with one button; the logo is
     * uploaded and removed on its own. The business names in other languages come from v1/client/business/switch;
     * when they cannot be fetched, their fields are left out, so they are not saved as empty.
     */
    public function index(): string
    {
        [$code, $body] = $this->callApi('v1/client/website', ['business_slug' => (string) currentBusinessSlug()]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException((string) ($body['message'] ?? lang('Website.load-failed')));
        }

        return view('website/index', [
            'locale'      => $this->request->getLocale(),
            'slug'        => 'website',
            'page_title'  => lang('Website.page-title'),
            'breadcrumbs' => [base_url('admin/business') => lang('System.page.business')],
            'user'        => (array) session('user'),
            'settings'    => (array) ($body['data'] ?? []),
            'names'       => $this->businessNames(),
            'languageCap' => self::languageCap((array) ($body['data']['options'] ?? [])),
            'urls'        => [
                'save'       => base_url('admin/business/website/save'),
                'logoUpload' => base_url('admin/business/website/logo-upload'),
                'logoDelete' => base_url('admin/business/website/logo-delete'),
            ],
        ]);
    }

    /**
     * Save the colours, languages and featured products and services through LINK_INTERNAL_API v1/client/website/save;
     * the page's script posts them as JSON { colors, languages, featured, names? }. names (language_code =>
     * business_name), sent only when changed, are saved first through v1/client/business/edit, which only owners may
     * do (a manager's are ignored: the page shows them disabled); the names in languages not posted are kept (fetched
     * afresh, as the API replaces them all). The API refuses more languages than the plan allows (409, errors.languages).
     * @return ResponseInterface { success, message, settings } or { success: false, message, errors } with the API's
     *                           field names (languages.0.heading_font, translations.th), which the script shows by them
     */
    public function save(): ResponseInterface
    {
        $input   = (array) ($this->request->getJSON(true) ?? []);
        if (is_array($input['names'] ?? null) && [] !== $input['names'] && isBusinessOwner()) {
            $names = $this->businessNames();
            if (null === $names) {
                return $this->failed(500, [], lang('Website.save-failed'));
            }
            foreach ($input['names'] as $language => $name) {
                $names[(string) $language] = trim((string) $name);
            }
            [$code, $body] = $this->callApi('v1/client/business/edit', [
                'business_slug' => (string) currentBusinessSlug(),
                'business'      => (object) [], // every field kept
                'translations'  => (object) $names,
            ]);
            if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
                return $this->failed($code, $body, lang('Website.save-failed'), (array) ($body['errors'] ?? []));
            }
        }
        $payload = ['business_slug' => (string) currentBusinessSlug()] + array_intersect_key($input, array_flip(self::PARTS));
        [$code, $body] = $this->callApi('v1/client/website/save', $payload);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->failed($code, $body, lang('Website.save-failed'), (array) ($body['errors'] ?? []));
        }

        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('Website.saved'),
            'settings'  => (array) ($body['data'] ?? []),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Upload the logo through LINK_INTERNAL_API v1/client/website/logo-upload, multipart { business_slug, image }
     * @return ResponseInterface { success, message, logo: {url, shape} }
     */
    public function logoUpload(): ResponseInterface
    {
        $file = $this->request->getFile('image');
        if (null === $file || ! $file->isValid()) {
            $tooLarge = null !== $file && in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            return $this->failed($tooLarge ? 413 : 422, ['message' => lang($tooLarge ? 'Website.logo.too-large' : 'Website.logo.choose')], lang('Website.logo.failed'));
        }
        [$code, $body] = $this->callApi('v1/client/website/logo-upload', [
            'business_slug' => (string) currentBusinessSlug(),
            'image'         => new CURLFile($file->getTempName(), $file->getMimeType(), $file->getClientName()),
        ], true);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->failed($code, $body, lang('Website.logo.failed'));
        }

        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('Website.logo.uploaded'),
            'logo'      => (array) ($body['data'] ?? []),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Remove the logo through LINK_INTERNAL_API v1/client/website/logo-delete { business_slug }
     */
    public function logoDelete(): ResponseInterface
    {
        [$code, $body] = $this->callApi('v1/client/website/logo-delete', ['business_slug' => (string) currentBusinessSlug()]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->failed($code, $body, lang('Website.logo.failed'));
        }

        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('Website.logo.deleted'),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * The business's plan and how many languages it allows, from the options of v1/client/website (the API's
     * PlanLimits, which the save keeps to): { plan, name, max }, max 0 when the API gives none
     * @param array $options
     */
    private static function languageCap(array $options): array
    {
        $plan = (string) ($options['plan_code'] ?? '');
        return ['plan' => $plan, 'name' => planName($plan), 'max' => (int) ($options['max_languages'] ?? 0)];
    }

    /**
     * The session business's names in other languages (language_code => business_name), through
     * v1/client/business/switch; null when they cannot be fetched
     */
    private function businessNames(): ?array
    {
        [$code, $body] = $this->callApi('v1/client/business/switch', ['business_slug' => (string) currentBusinessSlug()]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return null;
        }
        return array_map('strval', (array) ($body['data']['translations'] ?? []));
    }

    /**
     * The script's answer to a failed API call
     * @param int    $code     HTTP status of the API call
     * @param array  $body     decoded API response
     * @param string $fallback message when the API gives none
     * @param array  $errors   field errors, by the API's names
     */
    private function failed(int $code, array $body, string $fallback, array $errors = []): ResponseInterface
    {
        return $this->response->setStatusCode($code === 200 ? 502 : $code)->setJSON([
            'success'   => false,
            'message'   => $body['message'] ?? $fallback,
            'errors'    => $errors ?: null,
            'csrf_hash' => csrf_hash(),
        ]);
    }
}
