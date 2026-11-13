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
    $routes->get('profile', 'Admin::profile');
    $routes->post('profile/save', 'Admin::profileEditPost');
    $routes->post('profile/password', 'Admin::profilePasswordPost');
    $routes->post('image-upload', 'Admin::imageUpload');
    $routes->post('image-delete', 'Admin::imageDelete');
    $routes->get('my-businesses', 'Admin::myBusinesses');
    $routes->post('my-businesses/data', 'Admin::myBusinessesData');
    $routes->post('my-businesses/make-default', 'Admin::myBusinessesMakeDefault');
    $routes->post('my-businesses/switch', 'Admin::myBusinessesSwitch');
    // the public profiles of the people of the session business: anyone active in it
    $routes->get('public-profiles', 'Employees::publicProfiles');
    $routes->get('public-profile/(:any)', 'Employees::publicProfile/$1');
    // the session business and its stores: its owners and managers only (see BusinessManagerFilter)
    $routes->group('business', ['filter' => 'manager'], static function ($routes) {
        $routes->get('/', 'Admin::business');
        $routes->post('save', 'Admin::businessEditPost');
        $routes->get('stores', 'Admin::stores');
        $routes->post('stores/data', 'Admin::storesData');
        $routes->get('stores/create', 'Admin::storeForm');
        $routes->get('stores/edit/(:segment)', 'Admin::storeForm/$1');
        $routes->post('stores/save', 'Admin::storeSavePost');
        // a store's opening hours; see App\Controllers\StoreHours
        $routes->get('stores/hours/(:segment)', 'StoreHours::index/$1');
        $routes->post('stores/hours/save', 'StoreHours::save');
        $routes->post('stores/hours/exception-save', 'StoreHours::exceptionSave');
        $routes->post('stores/hours/exception-delete', 'StoreHours::exceptionDelete');
        // the kinds of resource the services need, and the real ones at each store; see App\Controllers\Resources
        $routes->get('resource-types', 'Resources::types');
        $routes->post('resource-types/data', 'Resources::typesData');
        $routes->get('resource-types/create', 'Resources::typeForm');
        $routes->get('resource-types/edit/(:segment)', 'Resources::typeForm/$1');
        $routes->post('resource-types/save', 'Resources::typeSave');
        $routes->get('resources', 'Resources::index');
        $routes->post('resources/data', 'Resources::data');
        $routes->get('resources/create', 'Resources::form');
        $routes->get('resources/edit/(:segment)', 'Resources::form/$1');
        $routes->post('resources/save', 'Resources::save');
        // how the business's customers can pay it, at every store or at one; see App\Controllers\PaymentMethods
        $routes->get('payment-methods', 'PaymentMethods::index');
        $routes->post('payment-methods/data', 'PaymentMethods::data');
        $routes->get('payment-methods/create', 'PaymentMethods::form');
        $routes->get('payment-methods/edit/(:segment)', 'PaymentMethods::form/$1');
        $routes->post('payment-methods/save', 'PaymentMethods::save');
        // how the business's web pages look; see App\Controllers\Website
        $routes->get('website', 'Website::index');
        $routes->post('website/save', 'Website::save');
        $routes->post('website/logo-upload', 'Website::logoUpload');
        $routes->post('website/logo-delete', 'Website::logoDelete');
        // how the business pays commission; see App\Controllers\Staff
        $routes->get('commission', 'Staff::commission');
        $routes->post('commission/save', 'Staff::commissionSave');
    });
    // where the people of the session business work and what they are paid: its owners and managers, as the API allows
    $routes->group('staff', ['filter' => 'manager'], static function ($routes) {
        $routes->get('/', 'Staff::index');
        $routes->post('data', 'Staff::data');
        $routes->get('edit/(:segment)', 'Staff::form/$1');
        $routes->post('stores/save', 'Staff::saveStores');
        $routes->post('services/save', 'Staff::saveServices');
        $routes->post('salary/add', 'Staff::addSalary');
        $routes->post('salary/edit', 'Staff::editSalary');
        $routes->get('hours/(:segment)', 'Staff::hours/$1');
        $routes->post('hours/save', 'Staff::hoursSave');
        $routes->post('hours/exception-save', 'Staff::hoursExceptionSave');
        $routes->post('hours/exception-delete', 'Staff::hoursExceptionDelete');
        // what the business records about its people; see App\Controllers\StaffAttributes
        $routes->get('attributes', 'StaffAttributes::index');
        $routes->post('attributes/data', 'StaffAttributes::data');
        $routes->get('attributes/create', 'StaffAttributes::form');
        $routes->get('attributes/edit/(:segment)', 'StaffAttributes::form/$1');
        $routes->post('attributes/save', 'StaffAttributes::save');
        $routes->post('attributes/delete', 'StaffAttributes::delete');
        // one person's languages, details and restrictions; see App\Controllers\StaffProfiles
        $routes->get('profile/(:segment)', 'StaffProfiles::form/$1');
        $routes->post('profile/languages-save', 'StaffProfiles::saveLanguages');
        $routes->post('profile/details-save', 'StaffProfiles::saveDetails');
        $routes->post('profile/restrictions-save', 'StaffProfiles::saveRestrictions');
    });
    // the session business's subscription: its owners only, as the API allows
    $routes->group('plans', ['filter' => 'manager:owner'], static function ($routes) {
        $routes->get('/', 'Plans::index');
        $routes->post('discounts', 'Plans::discounts');
        $routes->post('subscribe', 'Plans::subscribe');
        $routes->post('apply-discount', 'Plans::applyDiscount');
        $routes->post('pay', 'Plans::pay');
        $routes->post('cancel', 'Plans::cancel');
        $routes->post('resume', 'Plans::resume');
        $routes->post('change', 'Plans::change');
        $routes->post('withholding', 'Plans::withholding');
        $routes->post('withholding/withdraw', 'Plans::withdrawWithholding');
        $routes->post('certificate', 'Plans::certificate');
        $routes->get('document/(invoice|receipt)/(:segment)', 'Plans::document/$1/$2');
    });
    // the people of the session business and their accounts: its owners only, as the API allows
    $routes->group('employees', ['filter' => 'manager:owner'], static function ($routes) {
        $routes->get('/', 'Employees::index');
        $routes->post('data', 'Employees::data');
        $routes->get('create', 'Employees::create');
        $routes->get('edit/(:segment)', 'Employees::form/$1');
        $routes->post('save', 'Employees::save');
        $routes->post('terminate', 'Employees::terminate');
        $routes->post('rehire', 'Employees::rehire');
        $routes->post('find', 'Employees::find');
        $routes->post('add', 'Employees::add');
        $routes->post('invite', 'Employees::invite');
    });
    // the sessions of the session business's group services: its owners and managers only (see BusinessManagerFilter)
    $routes->group('sessions', ['filter' => 'manager'], static function ($routes) {
        $routes->get('/', 'ServiceSessions::index');
        $routes->post('data', 'ServiceSessions::data');
        $routes->get('create', 'ServiceSessions::form');
        $routes->get('edit/(:segment)', 'ServiceSessions::form/$1');
        $routes->post('save', 'ServiceSessions::save');
        $routes->post('options', 'ServiceSessions::options');
    });
    // the products and services of the session business: its owners and managers only (see BusinessManagerFilter)
    $routes->group('artifacts', ['filter' => 'manager'], static function ($routes) {
        $routes->get('/', 'Artifacts::index');
        $routes->post('data', 'Artifacts::data');
        $routes->get('create', 'Artifacts::form');
        $routes->get('edit/(:segment)', 'Artifacts::form/$1');
        $routes->post('save', 'Artifacts::save');
        $routes->post('image-upload', 'Artifacts::imageUpload');
        $routes->post('image-delete', 'Artifacts::imageDelete');
        $routes->post('image-reorder', 'Artifacts::imageReorder');
        // the resources a service needs; see App\Controllers\Resources
        $routes->post('resources/save', 'Resources::saveNeeds');
        // what the products and services are grouped by, and what their variants differ by
        $routes->get('categories', 'ArtifactCategories::index');
        $routes->post('categories/data', 'ArtifactCategories::data');
        $routes->get('categories/create', 'ArtifactCategories::form');
        $routes->get('categories/edit/(:segment)', 'ArtifactCategories::form/$1');
        $routes->post('categories/save', 'ArtifactCategories::save');
        $routes->get('attributes', 'ArtifactAttributes::index');
        $routes->post('attributes/data', 'ArtifactAttributes::data');
        $routes->get('attributes/create', 'ArtifactAttributes::form');
        $routes->get('attributes/edit/(:segment)', 'ArtifactAttributes::form/$1');
        $routes->post('attributes/save', 'ArtifactAttributes::save');
    });
    // who visited the session business's marketplace pages, and how many ordered: its owners and managers only,
    // as the API allows; see App\Controllers\Traffic
    $routes->group('traffic', ['filter' => 'manager'], static function ($routes) {
        $routes->get('/', 'Traffic::index');
        $routes->post('data/(overview|breakdown|visits)', 'Traffic::data/$1');
    });
    // the reviews of the session business's products and services: anyone working in it, as the API allows
    $routes->get('reviews', 'Reviews::index');
    $routes->post('reviews/data', 'Reviews::data');
    $routes->post('reviews/approve', 'Reviews::approve');
    $routes->post('reviews/hide', 'Reviews::hide');
    // the signed-in client's notifications, by public_id; see App\Controllers\Notifications
    $routes->get('notifications', 'Notifications::index');
    $routes->post('notifications/latest', 'Notifications::latest');
    $routes->post('notifications/list', 'Notifications::list');
    $routes->get('notifications/open/(:segment)', 'Notifications::open/$1');
    $routes->post('notifications/read-all', 'Notifications::readAll');
    $routes->post('notifications/delete', 'Notifications::delete');
    $routes->post('notifications/delete-read', 'Notifications::deleteRead');
    $routes->get('messages', 'Admin::messages');

    // JSON lookups for the pages' scripts; see App\Controllers\ApiList
    $routes->post('api/telephone-format-e164', 'ApiList::telephoneFormatE164');
    $routes->match(['GET', 'POST'], 'api/subdivisions', 'ApiList::subdivisions');
    $routes->match(['GET', 'POST'], 'api/currencies', 'ApiList::currencies');
    $routes->match(['GET', 'POST'], 'api/generate-slug', 'ApiList::generateSlug');
});