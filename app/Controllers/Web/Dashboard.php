<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use CodeIgniter\I18n\Time;

/**
 * GET /app?date=Y-m-d - the owner's home screen (shares DashboardService with the JSON API).
 */
class Dashboard extends WebController
{
    public function index(): string
    {
        $date = (string) ($this->request->getGet('date') ?? '');

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false) {
            $date = Time::now()->toDateString();
        }

        $day = Time::parse($date);

        return $this->render('web/dashboard', [
            'title' => 'Dashboard',
            'date'  => $date,
            'prev'  => $day->subDays(1)->toDateString(),
            'next'  => $day->addDays(1)->toDateString(),
            'd'     => service('dashboard')->build($date),
        ], 'dashboard');
    }
}
