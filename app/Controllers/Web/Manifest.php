<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Database\Seeds\SettingsSeeder;
use App\Models\SettingModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * GET /manifest.webmanifest - web app manifest so "Add to Home Screen" opens /app full screen.
 * Generated from settings so the parlour name on the phone follows the settings page.
 */
class Manifest extends BaseController
{
    public function index(): ResponseInterface
    {
        $name = trim((string) ((new SettingModel())->value('parlour_name') ?? '')) ?: SettingsSeeder::DEFAULTS['parlour_name'];

        return $this->response
            ->setJSON([
                'name'             => $name,
                'short_name'       => mb_substr($name, 0, 12),
                'description'      => 'Owner app: appointments, customers, payments and WhatsApp reminders.',
                'lang'             => 'gu',
                'start_url'        => site_url('app'),
                'scope'            => site_url('/'),
                'display'          => 'standalone',
                'orientation'      => 'portrait',
                'background_color' => '#fbf6f8',
                'theme_color'      => '#b4236b',
                'icons'            => [
                    ['src' => base_url('assets/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                    ['src' => base_url('assets/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                    ['src' => base_url('assets/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
                ],
            ])
            ->setContentType('application/manifest+json');
    }
}
