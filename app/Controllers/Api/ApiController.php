<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;

/**
 * Shared helpers for the bearer-protected JSON endpoints used by the Flutter app.
 */
abstract class ApiController extends BaseController
{
    use ResponseTrait;

    protected const PER_PAGE_DEFAULT = 25;
    protected const PER_PAGE_MAX     = 100;

    /**
     * The authenticated owner row (without secrets) - the ownerauth filter guarantees it exists.
     */
    protected function owner(): array
    {
        $owner = service('ownerAuth')->owner() ?? [];
        unset($owner['password_hash']);

        return $owner;
    }

    /**
     * Request payload: JSON body first, otherwise form fields (POST or raw PUT/PATCH).
     *
     * @return array<string, mixed>
     */
    protected function input(): array
    {
        $json = $this->request->getJSON(true);

        if (is_array($json)) {
            return $json;
        }

        return array_merge($this->request->getRawInput(), $this->request->getPost());
    }

    protected function validationFailure(): ResponseInterface
    {
        return $this->failValidationErrors($this->validator->getErrors());
    }

    /**
     * @return array{0:int,1:int} [page, perPage]
     */
    protected function pageParams(): array
    {
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = (int) ($this->request->getGet('per_page') ?? self::PER_PAGE_DEFAULT);
        $perPage = max(1, min(self::PER_PAGE_MAX, $perPage));

        return [$page, $perPage];
    }

    /**
     * Pager details in a compact, Flutter-friendly form.
     */
    protected function pagerInfo(object $pager): array
    {
        return [
            'page'      => $pager->getCurrentPage(),
            'per_page'  => $pager->getPerPage(),
            'total'     => $pager->getTotal(),
            'pages'     => $pager->getPageCount(),
        ];
    }

    /**
     * A date query parameter (Y-m-d) or today's local date.
     */
    protected function dateParam(string $name, ?string $default = null): string
    {
        $value = (string) ($this->request->getGet($name) ?? '');

        if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false) {
            return $value;
        }

        return $default ?? Time::now()->toDateString();
    }

    protected function now(): string
    {
        return Time::now()->toDateTimeString();
    }
}
