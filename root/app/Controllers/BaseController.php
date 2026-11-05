<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    // protected $session;

    /**
     * @param CLIRequest|IncomingRequest $request
     *
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        // $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        // $this->session = service('session');

        $this->applyPreferredLanguage();
    }

    /**
     * POST to LINK_INTERNAL_API with the signed-in client's JWT and return [http status, decoded body]
     * @param bool $multipart true: sent as multipart/form-data (files as CURLFile), otherwise as JSON
     */
    protected function callApi(string $endpoint, array $payload, bool $multipart = false): array
    {
        try {
            $response = service('curlrequest')->post(getenv('LINK_INTERNAL_API') . $endpoint, [
                ($multipart ? 'multipart' : 'json') => $payload,
                'headers'     => [
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . session()->get('access_token'),
                ],
                'timeout'     => 15,
                'http_errors' => false,
            ]);
            $code = $response->getStatusCode();
            $body = json_decode($response->getBody(), true);
            // a 404 without the API's own error body means the endpoint is missing, not the record
            if (! is_array($body) || $code >= 500 || ($code === 404 && ($body['status'] ?? '') !== 'error')) {
                log_message('error', 'LINK_INTERNAL_API ' . $endpoint . ' returned HTTP ' . $code);
                return [503, ['message' => lang('Login.error.service-unavailable')]];
            }
            return [$code, $body];
        } catch (Throwable $e) {
            log_message('error', 'LINK_INTERNAL_API ' . $endpoint . ' failed: ' . $e->getMessage());
            return [503, ['message' => lang('Login.error.service-unavailable')]];
        }
    }

    /**
     * A signed-in client sees the UI in client_master.preferred_language (kept in the session user from sign-in,
     * and updated when the profile is saved) instead of the browser's language, when the UI has that language
     */
    private function applyPreferredLanguage(): void
    {
        if (! $this->request instanceof IncomingRequest) {
            return;
        }
        $language = (string) (session('user')['preferred_language'] ?? '');
        if (! isset(listSupportedLanguages()[$language]) || ! in_array($language, config('App')->supportedLocales, true)) {
            return;
        }
        $this->request->setLocale($language);
        // lang() keeps the locale its shared service was created with, so it is told too
        service('language')->setLocale($language);
    }
}
