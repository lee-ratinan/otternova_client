<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

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
