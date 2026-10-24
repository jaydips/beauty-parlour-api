<?php

namespace Config;

use App\Libraries\AppointmentService;
use App\Libraries\DashboardService;
use App\Libraries\Outbox;
use App\Libraries\OwnerAuth;
use App\Libraries\WhatsApp;
use App\Libraries\WhatsAppBridge;
use CodeIgniter\Config\BaseService;

/**
 * Application services. Call them as service('ownerAuth'), service('whatsapp'), service('appointments').
 */
class Services extends BaseService
{
    /**
     * Request-scoped owner authentication state (bearer tokens).
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
     * HTTP client for the Node.js WhatsApp bridge (tests inject a fake with Services::injectMock).
     */
    public static function whatsappBridge(bool $getShared = true): WhatsAppBridge
    {
        if ($getShared) {
            return static::getSharedInstance('whatsappBridge');
        }

        return new WhatsAppBridge();
    }

    /**
     * Automatic WhatsApp outbox: queueing, campaigns and delivery through the bridge.
     */
    public static function outbox(bool $getShared = true): Outbox
    {
        if ($getShared) {
            return static::getSharedInstance('outbox');
        }

        return new Outbox();
    }

    /**
     * Home-screen data shared by the JSON API and the web UI.
     */
    public static function dashboard(bool $getShared = true): DashboardService
    {
        if ($getShared) {
            return static::getSharedInstance('dashboard');
        }

        return new DashboardService();
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
}
