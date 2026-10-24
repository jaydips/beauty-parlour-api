<?php

namespace Config;

use App\Libraries\AppointmentService;
use App\Libraries\CustomerService;
use App\Libraries\DashboardService;
use App\Libraries\OwnerAuth;
use App\Libraries\ReminderService;
use App\Libraries\WhatsApp;
use CodeIgniter\Config\BaseService;

/**
 * Application services. Call them as service('ownerAuth'), service('whatsapp'),
 * service('appointments'), service('reminders'), service('customers'), service('dashboard').
 */
class Services extends BaseService
{
    /**
     * Request-scoped owner authentication state (bearer tokens for the API, PHP session for the web UI).
     */
    public static function ownerAuth(bool $getShared = true): OwnerAuth
    {
        if ($getShared) {
            return static::getSharedInstance('ownerAuth');
        }

        return new OwnerAuth();
    }

    /**
     * Click-to-chat link builder and message template renderer.
     */
    public static function whatsapp(bool $getShared = true): WhatsApp
    {
        if ($getShared) {
            return static::getSharedInstance('whatsapp');
        }

        return new WhatsApp();
    }

    /**
     * Appointment domain logic (items, totals, overlaps, reminders, status changes).
     */
    public static function appointments(bool $getShared = true): AppointmentService
    {
        if ($getShared) {
            return static::getSharedInstance('appointments');
        }

        return new AppointmentService();
    }

    /**
     * Reminder decoration (wa.me links), custom reminders and status changes.
     */
    public static function reminders(bool $getShared = true): ReminderService
    {
        if ($getShared) {
            return static::getSharedInstance('reminders');
        }

        return new ReminderService();
    }

    /**
     * Customer search, create-or-restore and visit statistics.
     */
    public static function customers(bool $getShared = true): CustomerService
    {
        if ($getShared) {
            return static::getSharedInstance('customers');
        }

        return new CustomerService();
    }

    /**
     * Home-screen data (appointments of the day, due reminders, revenue, dues).
     */
    public static function dashboard(bool $getShared = true): DashboardService
    {
        if ($getShared) {
            return static::getSharedInstance('dashboard');
        }

        return new DashboardService();
    }
}
