<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * GET /dashboard?date=Y-m-d (default today, local time)
 * One call for the app's home screen; the data is assembled by App\Libraries\DashboardService,
 * which the web UI (/app) shares.
 */
class Dashboard extends ApiController
{
    public function index(): ResponseInterface
    {
        return $this->respond(service('dashboard')->build($this->dateParam('date')));
    }
}
