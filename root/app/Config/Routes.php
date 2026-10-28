<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->get('/forgot-password', 'Home::forgotPassword');
$routes->get('/reset-password', 'Home::resetPassword');

$routes->group('admin', [], static function($routes) {

});