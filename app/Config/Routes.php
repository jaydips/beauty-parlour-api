<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// API root (JSON) - no HTML front end exists
$routes->get('/', 'Home::index');

// Unauthenticated liveness check used after every deployment.
// The app is served from https://delicate.jainamagrifood.com/, so this is /health there.
$routes->get('health', 'Api\Health::index');

// Web-triggered installer for SSH-less shared hosting.
// Every route answers 404 unless `.env` defines install.key AND ?key= matches it.
$routes->get('install/status', 'Install::status');
$routes->get('install/migrate', 'Install::migrate');
$routes->post('install/owner', 'Install::owner'); // one-time creation of the owner login

// ---------------------------------------------------------------------------
// Mobile web UI (server-rendered, browser session). Pages live under /app so
// they never collide with the JSON API routes at the root.
// ---------------------------------------------------------------------------
$routes->get('login', 'Web\Login::show');
$routes->post('login', 'Web\Login::attempt', ['filter' => 'csrf']);
$routes->post('logout', 'Web\Login::logout', ['filter' => 'csrf']);

$routes->group('app', ['filter' => ['webauth', 'csrf']], static function (RouteCollection $routes): void {
    $routes->get('/', 'Web\Dashboard::index');

    $routes->get('appointments', 'Web\Appointments::index');
    $routes->get('appointments/new', 'Web\Appointments::create');
    $routes->post('appointments', 'Web\Appointments::store');
    $routes->get('appointments/(:num)', 'Web\Appointments::show/$1');
    $routes->get('appointments/(:num)/edit', 'Web\Appointments::edit/$1');
    $routes->post('appointments/(:num)', 'Web\Appointments::update/$1');
    $routes->post('appointments/(:num)/status', 'Web\Appointments::status/$1');
    $routes->post('appointments/(:num)/delete', 'Web\Appointments::delete/$1');

    $routes->get('customers', 'Web\Customers::index');
    $routes->get('customers/search', 'Web\Customers::search');
    $routes->get('customers/new', 'Web\Customers::create');
    $routes->post('customers', 'Web\Customers::store');
    $routes->get('customers/(:num)', 'Web\Customers::show/$1');
    $routes->get('customers/(:num)/edit', 'Web\Customers::edit/$1');
    $routes->post('customers/(:num)', 'Web\Customers::update/$1');
    $routes->post('customers/(:num)/delete', 'Web\Customers::delete/$1');

    $routes->post('payments', 'Web\Payments::store');
    $routes->post('payments/(:num)/delete', 'Web\Payments::delete/$1');

    $routes->post('reminders/(:num)/sent', 'Web\Reminders::sent/$1');
    $routes->post('reminders/(:num)/skip', 'Web\Reminders::skip/$1');
});

// ---------------------------------------------------------------------------
// JSON API for the Flutter app
// ---------------------------------------------------------------------------
// Owner login (phone + password) -> bearer token
$routes->post('auth/login', 'Auth::login');

// Everything below requires `Authorization: Bearer <token>`
$routes->group('', ['filter' => 'ownerauth'], static function (RouteCollection $routes): void {
    $routes->post('auth/logout', 'Auth::logout');
    $routes->get('auth/me', 'Auth::me');
    $routes->put('auth/profile', 'Auth::profile');
    $routes->put('auth/password', 'Auth::password');
    $routes->get('auth/devices', 'Auth::devices');
    $routes->delete('auth/devices/(:num)', 'Auth::revokeDevice/$1');

    $routes->get('dashboard', 'Api\Dashboard::index');

    $routes->get('settings', 'Api\Settings::index');
    $routes->put('settings', 'Api\Settings::update');

    $routes->get('services', 'Api\Services::index');
    $routes->post('services', 'Api\Services::create');
    $routes->get('services/(:num)', 'Api\Services::show/$1');
    $routes->put('services/(:num)', 'Api\Services::update/$1');
    $routes->delete('services/(:num)', 'Api\Services::delete/$1');

    $routes->get('customers', 'Api\Customers::index');
    $routes->post('customers', 'Api\Customers::create');
    $routes->get('customers/(:num)', 'Api\Customers::show/$1');
    $routes->put('customers/(:num)', 'Api\Customers::update/$1');
    $routes->delete('customers/(:num)', 'Api\Customers::delete/$1');

    $routes->get('appointments', 'Api\Appointments::index');
    $routes->post('appointments', 'Api\Appointments::create');
    $routes->get('appointments/(:num)', 'Api\Appointments::show/$1');
    $routes->put('appointments/(:num)', 'Api\Appointments::update/$1');
    $routes->patch('appointments/(:num)/status', 'Api\Appointments::status/$1');
    $routes->delete('appointments/(:num)', 'Api\Appointments::delete/$1');

    $routes->get('payments', 'Api\Payments::index');
    $routes->post('payments', 'Api\Payments::create');
    $routes->get('payments/(:num)', 'Api\Payments::show/$1');
    $routes->delete('payments/(:num)', 'Api\Payments::delete/$1');

    $routes->get('reminders', 'Api\Reminders::index');
    $routes->post('reminders', 'Api\Reminders::create');
    $routes->get('reminders/(:num)', 'Api\Reminders::show/$1');
    $routes->patch('reminders/(:num)', 'Api\Reminders::updateStatus/$1');
    $routes->post('reminders/(:num)/sent', 'Api\Reminders::markSent/$1');

    $routes->get('reports/revenue', 'Api\Reports::revenue');
    $routes->get('reports/services', 'Api\Reports::services');
    $routes->get('reports/dues', 'Api\Reports::dues');
});

// JSON 404 for unknown routes (this is an API, not a website).
// CodeIgniter echoes a Closure override's return value and has already set
// the 404 status, so the closure must return a string.
$routes->set404Override(static function (): string {
    service('response')->setContentType('application/json');

    return json_encode(['status' => 404, 'error' => 'Not Found'], JSON_THROW_ON_ERROR);
});
