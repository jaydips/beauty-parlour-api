<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Requires `Authorization: Bearer <token>` issued by POST /auth/login.
 * Alias `ownerauth` in Config\Filters; applied per route group in Config\Routes.
 */
class OwnerAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $header = $request->getHeaderLine('Authorization');
        $raw    = '';

        if (preg_match('/^Bearer\s+([0-9a-fA-F]{64})$/', trim($header), $m)) {
            $raw = strtolower($m[1]);
        }

        if ($raw === '' || ! service('ownerAuth')->attempt($raw)) {
            return service('response')
                ->setStatusCode(401)
                ->setHeader('WWW-Authenticate', 'Bearer')
                ->setJSON(['status' => 401, 'error' => 'Unauthorized', 'message' => 'A valid bearer token is required.']);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
