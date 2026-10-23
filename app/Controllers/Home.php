<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * API root. There is no HTML front end; the Flutter app is the only client.
 */
class Home extends BaseController
{
    public function index(): ResponseInterface
    {
        return $this->response->setJSON([
            'app'    => 'beauty-parlour-api',
            'status' => 'ok',
            'health' => site_url('health'),
        ]);
    }
}
