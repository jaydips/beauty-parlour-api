<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// API root (JSON)
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
// JSON API for the Flutter app: bearer tokens
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

// ---------------------------------------------------------------------------
// Browser UI for the owner's phone: PHP session + CSRF, server-rendered views
// ---------------------------------------------------------------------------

$routes->get('login', 'Web\Auth::login');
$routes->post('login', 'Web\Auth::attempt', ['filter' => 'csrf']);
$routes->match(['GET', 'POST'], 'logout', 'Web\Auth::logout');
$routes->get('manifest.webmanifest', 'Web\Manifest::index');

$routes->group('app', ['namespace' => 'App\Controllers\Web', 'filter' => ['webauth', 'csrf']], static function (RouteCollection $routes): void {
    $routes->get('/', 'Dashboard::index');
    $routes->get('more', 'Dashboard::more');

    $routes->get('appointments', 'Appointments::index');
    $routes->get('appointments/new', 'Appointments::create');
    $routes->post('appointments', 'Appointments::store');
    $routes->get('appointments/(:num)', 'Appointments::show/$1');
    $routes->get('appointments/(:num)/edit', 'Appointments::edit/$1');
    $routes->post('appointments/(:num)', 'Appointments::update/$1');
    $routes->post('appointments/(:num)/status', 'Appointments::status/$1');
    $routes->post('appointments/(:num)/delete', 'Appointments::delete/$1');

    $routes->get('customers', 'Customers::index');
    $routes->get('customers/search', 'Customers::search'); // JSON for the picker widget
    $routes->get('customers/new', 'Customers::create');
    $routes->post('customers', 'Customers::store');
    $routes->get('customers/(:num)', 'Customers::show/$1');
    $routes->get('customers/(:num)/edit', 'Customers::edit/$1');
    $routes->post('customers/(:num)', 'Customers::update/$1');
    $routes->post('customers/(:num)/delete', 'Customers::delete/$1');

    $routes->get('services', 'Services::index');
    $routes->get('services/new', 'Services::create');
    $routes->post('services', 'Services::store');
    $routes->get('services/(:num)/edit', 'Services::edit/$1');
    $routes->post('services/(:num)', 'Services::update/$1');
    $routes->post('services/(:num)/toggle', 'Services::toggle/$1');
    $routes->post('services/(:num)/delete', 'Services::delete/$1');

    $routes->get('payments', 'Payments::index');
    $routes->get('payments/new', 'Payments::create');
    $routes->post('payments', 'Payments::store');
    $routes->post('payments/(:num)/delete', 'Payments::delete/$1');

    $routes->get('reminders', 'Reminders::index');
    $routes->get('reminders/new', 'Reminders::create');
    $routes->post('reminders', 'Reminders::store');
    $routes->post('reminders/(:num)/status', 'Reminders::status/$1');

    $routes->get('settings', 'Settings::index');
    $routes->post('settings', 'Settings::update');
    $routes->post('settings/password', 'Settings::password');
});

// 404: HTML for the browser UI paths, JSON for everything else (the API).
// CodeIgniter echoes a Closure override's return value and has already set
// the 404 status, so the closure must return a string.
$routes->set404Override(static function (): string {
    $path = ltrim(uri_string(), '/');

    if ($path === 'app' || str_starts_with($path, 'app/') || str_starts_with($path, 'login') || str_starts_with($path, 'logout')) {
        helper(['url', 'ui']);
        service('response')->setContentType('text/html');
        $auth = service('ownerAuth');

        return view('web/layout', [
            'title'       => 'પેજ મળ્યું નહીં',
            'loggedIn'    => $auth->check(),
            'parlourName' => (new \App\Models\SettingModel())->value('parlour_name') ?? 'Beauty Parlour',
            'content'     => view('web/partials/not_found'),
        ]);
    }

    service('response')->setContentType('application/json');

    return json_encode(['status' => 404, 'error' => 'Not Found'], JSON_THROW_ON_ERROR);
});
