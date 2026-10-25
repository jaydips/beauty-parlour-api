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
 * GET /health - unauthenticated liveness check used after each deployment.
 * GET /health?deep=1 adds what campaign picture uploads need: GD, the uploads directory, PHP limits.
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

        if ($this->request->getGet('deep')) {
            $payload['uploads'] = $this->uploads();
        }

        return $this->respond($payload, $db['ok'] ? 200 : 503);
    }

    /**
     * Everything a campaign picture upload depends on (booleans and limits only).
     *
     * @return array<string, mixed>
     */
    private function uploads(): array
    {
        $dir      = service('campaignImage')->dir();
        $exists   = is_dir($dir);
        $writable = $exists ? is_writable($dir) : (is_dir(dirname($dir)) && is_writable(dirname($dir)));
        $gd       = extension_loaded('gd');
        $info     = $gd ? gd_info() : [];
        $handler  = false;

        try {
            service('image')->withFile(FCPATH . 'favicon.ico'); // handler can be built; file may not be an image
            $handler = true;
        } catch (Throwable) {
            $handler = $gd; // a missing favicon is not a GD problem
        }

        return [
            'gd'                  => $gd,
            'gd_jpeg'             => (bool) ($info['JPEG Support'] ?? false),
            'gd_png'              => (bool) ($info['PNG Support'] ?? false),
            'gd_webp'             => (bool) ($info['WebP Support'] ?? false),
            'image_handler'       => $handler,
            'dir_exists'          => $exists,
            'dir_writable'        => $writable,
            'dir_in_docroot'      => str_starts_with($dir, rtrim(FCPATH, '/\\')),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size'       => ini_get('post_max_size'),
            'memory_limit'        => ini_get('memory_limit'),
            'file_uploads'        => (bool) ini_get('file_uploads'),
            'max_bytes_accepted'  => config('WhatsApp')->imageMaxBytes,
        ];
    }
}
