<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Database\Seeds\SettingsSeeder;
use App\Models\SettingModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\I18n\Time;

/**
 * Shared plumbing for the server-rendered, mobile-first owner UI under /app.
 * Views live in app/Views/web/, labels are Gujarati, every page is wrapped in web/layout.
 */
abstract class WebController extends BaseController
{
    /** Loaded for every web controller and therefore available in the views. */
    protected $helpers = ['form', 'url', 'ui'];

    /** Fallback cache-buster for assets when the file cannot be stat'ed. */
    private const ASSET_VERSION = '20261024';

    private ?array $settings = null;

    /**
     * Renders a page inside the common layout.
     *
     * @param array<string, mixed> $data view data; `title` and `tab` are used by the layout
     */
    protected function render(string $view, array $data = [], string $title = ''): string
    {
        $data += [
            'title'       => $title,
            'tab'         => null,
            'back'        => null,
            'loggedIn'    => service('ownerAuth')->check(),
            'owner'       => service('ownerAuth')->owner(),
            'parlourName' => $this->setting('parlour_name'),
            'assetVersion'=> $this->assetVersion(),
            'now'         => Time::now(),
        ];

        $data['content'] = view('web/' . $view, $data);

        return view('web/layout', $data);
    }

    /**
     * One setting value (seeded defaults as fallback).
     */
    protected function setting(string $key, ?string $default = null): ?string
    {
        if ($this->settings === null) {
            $this->settings = (new SettingModel())->allValues();
        }

        return $this->settings[$key] ?? $default ?? SettingsSeeder::DEFAULTS[$key] ?? null;
    }

    /**
     * Every setting with seeded defaults filled in.
     *
     * @return array<string, string|null>
     */
    protected function settings(): array
    {
        $this->setting('parlour_name');
        $values = $this->settings ?? [];

        foreach (SettingsSeeder::DEFAULTS as $key => $default) {
            $values[$key] ??= $default;
        }

        return $values;
    }

    /**
     * Redirect with a one-time status message shown by the layout.
     */
    protected function redirectWith(string $to, string $type, string $message): RedirectResponse
    {
        session()->setFlashdata($type, $message);

        return redirect()->to(str_starts_with($to, 'http') ? $to : site_url($to));
    }

    /**
     * Safe "return to" target posted by list pages: must be a path on this site.
     */
    protected function returnTo(string $default): string
    {
        $return = (string) ($this->request->getPost('return') ?? $this->request->getGet('return') ?? '');

        if ($return !== '' && str_starts_with($return, site_url('/'))) {
            return $return;
        }

        if ($return !== '' && preg_match('#^/[a-z0-9/_\-?=&%.]*$#i', $return) && ! str_starts_with($return, '//')) {
            return site_url(ltrim($return, '/'));
        }

        return site_url($default);
    }

    /**
     * A date query/post parameter (Y-m-d) or today's local date.
     */
    protected function dateParam(string $name, ?string $default = null): string
    {
        $value = (string) ($this->request->getGet($name) ?? $this->request->getPost($name) ?? '');

        if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false) {
            return $value;
        }

        return $default ?? Time::now()->toDateString();
    }

    /**
     * Trimmed POST fields; empty strings become null when $nullable.
     *
     * @param list<string> $fields
     *
     * @return array<string, mixed>
     */
    protected function post(array $fields, bool $nullable = false): array
    {
        $out = [];

        foreach ($fields as $field) {
            $value = $this->request->getPost($field);

            if (is_string($value)) {
                $value = trim($value);

                if ($nullable && $value === '') {
                    $value = null;
                }
            }

            $out[$field] = $value;
        }

        return $out;
    }

    private function assetVersion(): string
    {
        $css = FCPATH . 'assets/app.css';

        return is_file($css) ? (string) filemtime($css) : self::ASSET_VERSION;
    }
}
