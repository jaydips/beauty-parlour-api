<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\CodeIgniter;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use Config\Database;
use Throwable;

/**
 * GET /health (https://<domain>/api/health in production) - unauthenticated liveness
 * check used after each deployment.
 * Reports only non-sensitive facts (no paths, no credentials).
 */
class Health extends BaseController
{
    use ResponseTrait;

    public function index(): ResponseInterface
    {
        $db = ['ok' => false, 'driver' => null, 'version' => null];

        try {
            $conn = Database::connect();
            $conn->query('SELECT 1');
            $db = [
                'ok'      => true,
                'driver'  => $conn->getPlatform(),
                'version' => $conn->getVersion(),
            ];
        } catch (Throwable $e) {
            log_message('error', 'Health check database failure: {message}', ['message' => $e->getMessage()]);
        }

        $payload = [
            'status'      => $db['ok'] ? 'ok' : 'degraded',
            'app'         => 'beauty-parlour-api',
            'framework'   => CodeIgniter::CI_VERSION,
            'php'         => PHP_VERSION,
            'environment' => ENVIRONMENT,
            'time'        => Time::now()->toDateTimeString(),
            'timezone'    => app_timezone(),
            'database'    => $db,
        ];

        return $this->respond($payload, $db['ok'] ? 200 : 503);
    }
}
