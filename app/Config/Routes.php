<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// API root (JSON) - no HTML front end exists
$routes->get('/', 'Home::index');

// Unauthenticated liveness check used after every deployment.
// The app is served from https://<domain>/api/, so this is https://<domain>/api/health
$routes->get('health', 'Api\Health::index');

// Web-triggered migration runner for SSH-less shared hosting.
// Answers 404 unless `.env` defines install.key AND ?key= matches it.
$routes->get('install/status', 'Install::status');
$routes->get('install/migrate', 'Install::migrate');

// JSON 404 for unknown routes (this is an API, not a website).
// CodeIgniter echoes a Closure override's return value and has already set
// the 404 status, so the closure must return a string.
$routes->set404Override(static function (): string {
    service('response')->setContentType('application/json');

    return json_encode(['status' => 404, 'error' => 'Not Found'], JSON_THROW_ON_ERROR);
});
