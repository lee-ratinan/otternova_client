<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->post('/login', 'Home::login');
$routes->post('/verify-otp', 'Home::verifyOtp');
$routes->get('/logout', 'Home::logout');
$routes->get('/create-account', 'Home::createAccount');
$routes->get('/forgot-password', 'Home::forgotPassword');
$routes->get('/reset-password', 'Home::resetPassword');

$routes->group('admin', ['filter' => 'auth'], static function($routes) {
    $routes->get('dashboard', 'Admin::dashboard');
});