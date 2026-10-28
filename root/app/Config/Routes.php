<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->get('/create-account', 'Home::createAccount');
$routes->get('/forgot-password', 'Home::forgotPassword');
$routes->get('/reset-password', 'Home::resetPassword');

$routes->group('admin', [], static function($routes) {

});