<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Site root. A browser lands on the login page (or on the dashboard when a web session exists);
 * an API client that asks for JSON (Accept: application/json, no text/html) still gets the small
 * API root document, so `curl https://<host>/` and the Flutter app keep working.
 */
class Home extends BaseController
{
    public function index(): ResponseInterface|RedirectResponse
    {
        $accept = strtolower($this->request->getHeaderLine('Accept'));

        if (str_contains($accept, 'application/json') && ! str_contains($accept, 'text/html')) {
            return $this->response->setJSON([
                'app'    => 'beauty-parlour-api',
                'status' => 'ok',
                'health' => site_url('health'),
                'login'  => site_url('login'),
            ]);
        }

        return redirect()->to(site_url(session()->has('owner_id') ? 'app' : 'login'));
    }
}
