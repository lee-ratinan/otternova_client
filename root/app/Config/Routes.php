<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->post('/login', 'Home::login');
$routes->post('/verify-otp', 'Home::verifyOtp');
$routes->post('/change-password', 'Home::changePassword');
$routes->get('/logout', 'Home::logout');
$routes->get('/create-account', 'Home::createAccount');
$routes->post('/create-account', 'Home::submitCreateAccount');
$routes->get('/activate-account', 'Home::activateAccount');
$routes->get('/forgot-password', 'Home::forgotPassword');
$routes->post('/forgot-password', 'Home::submitForgotPassword');
$routes->get('/reset-password', 'Home::resetPassword');
$routes->post('/reset-password', 'Home::submitResetPassword');

$routes->group('admin', ['filter' => 'auth'], static function($routes) {
    $routes->get('/', 'Admin::dashboard');
    $routes->get('dashboard', 'Admin::dashboard');
    $routes->get('create-business', 'Admin::createBusiness');
    $routes->post('create-business', 'Admin::createBusinessPost');
    $routes->get('public-profile/(:any)', 'Admin::publicProfile/$1');
    $routes->get('profile', 'Admin::profile');
    $routes->post('profile/save', 'Admin::profileEditPost');
    $routes->post('profile/password', 'Admin::profilePasswordPost');
    $routes->post('image-upload', 'Admin::imageUpload');
    $routes->post('image-delete', 'Admin::imageDelete');
    $routes->get('my-businesses', 'Admin::myBusinesses');
    $routes->post('my-businesses/data', 'Admin::myBusinessesData');
    $routes->post('my-businesses/make-default', 'Admin::myBusinessesMakeDefault');
    $routes->post('my-businesses/switch', 'Admin::myBusinessesSwitch');
    // the session business and its stores: its owners and managers only (see BusinessManagerFilter)
    $routes->group('business', ['filter' => 'manager'], static function ($routes) {
        $routes->get('/', 'Admin::business');
        $routes->post('save', 'Admin::businessEditPost');
        $routes->get('stores', 'Admin::stores');
        $routes->post('stores/data', 'Admin::storesData');
        $routes->get('stores/create', 'Admin::storeForm');
        $routes->get('stores/edit/(:segment)', 'Admin::storeForm/$1');
        $routes->post('stores/save', 'Admin::storeSavePost');
    });
    $routes->get('notifications', 'Admin::notifications');
    $routes->get('messages', 'Admin::messages');

    // JSON lookups for the pages' scripts; see App\Controllers\ApiList
    $routes->post('api/telephone-format-e164', 'ApiList::telephoneFormatE164');
    $routes->match(['GET', 'POST'], 'api/subdivisions', 'ApiList::subdivisions');
    $routes->match(['GET', 'POST'], 'api/currencies', 'ApiList::currencies');
    $routes->match(['GET', 'POST'], 'api/generate-slug', 'ApiList::generateSlug');
});