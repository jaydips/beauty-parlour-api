<?php

declare(strict_types=1);

namespace App\Controllers\Web;

/**
 * GET /app?date=Y-m-d - the owner's home screen (today by default).
 * Data comes from App\Libraries\DashboardService, the same as GET /dashboard in the API.
 */
class Dashboard extends WebController
{
    public function index(): string
    {
        $date = $this->dateParam('date');
        $data = service('dashboard')->data($date);

        return $this->render('dashboard/index', [
            'd'    => $data,
            'date' => $date,
            'tab'  => 'home',
        ], 'ડેશબોર્ડ');
    }

    /**
     * GET /app/more - the fifth tab: reminders, services, payments, settings, logout.
     */
    public function more(): string
    {
        return $this->render('more', ['tab' => 'more'], 'વધુ');
    }
}
