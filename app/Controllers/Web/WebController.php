<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Models\OwnerModel;
use App\Models\SettingModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Shared behaviour for the server-rendered mobile web UI (/app/...).
 * Pages are protected by the `webauth` filter (browser session) and `csrf`.
 */
abstract class WebController extends BaseController
{
    protected $helpers = ['form', 'url', 'format'];

    private ?array $ownerCache = null;

    /**
     * The signed-in owner row without secrets.
     */
    protected function owner(): array
    {
        if ($this->ownerCache === null) {
            $owner = (new OwnerModel())->getOwner() ?? [];
            unset($owner['password_hash']);
            $this->ownerCache = $owner;
        }

        return $this->ownerCache;
    }

    /**
     * Renders a view inside the app layout with the common data every page needs.
     *
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = [], string $active = ''): string
    {
        $data += [
            'owner'   => $this->owner(),
            'active'  => $active,
            'parlour' => (string) (new SettingModel())->value('parlour_name', 'Beauty Parlour'),
            'title'   => $data['title'] ?? 'Beauty Parlour',
        ];

        return view($view, $data);
    }

    /**
     * Form fields (POST) as an associative array.
     *
     * @return array<string, mixed>
     */
    protected function input(): array
    {
        return $this->request->getPost() ?? [];
    }

    protected function back(string $fallback = 'app'): RedirectResponse
    {
        $referer = (string) $this->request->getServer('HTTP_REFERER');

        return $referer !== '' && str_starts_with($referer, base_url())
            ? redirect()->to($referer)
            : redirect()->to(site_url($fallback));
    }
}
