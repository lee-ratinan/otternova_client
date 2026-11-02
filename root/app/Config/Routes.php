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
$routes->get('/reset-password', 'Home::resetPassword');

$routes->group('admin', ['filter' => 'auth'], static function($routes) {
    $routes->get('/', 'Admin::dashboard');
    $routes->get('dashboard', 'Admin::dashboard');
});