<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * GET /dashboard?date=Y-m-d (default today, local time)
 * One call for the app's home screen: the day's appointments, due reminders with wa.me links,
 * today's and this month's revenue, pending dues and a few counters.
 * The data is assembled by App\Libraries\DashboardService, shared with the web UI.
 */
class Dashboard extends ApiController
{
    public function index(): ResponseInterface
    {
        return $this->respond(service('dashboard')->data($this->dateParam('date')));
    }
}
