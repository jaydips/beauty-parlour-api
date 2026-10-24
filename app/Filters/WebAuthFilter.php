<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\OwnerAuth;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Browser session authentication for the web UI under /app.
 * Alias `webauth` in Config\Filters. The session is created by POST /login
 * (App\Controllers\Web\Auth) and carries the owner id plus a key derived from
 * the password hash, so changing the password logs every other browser out.
 */
class WebAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        $ownerId = (int) $session->get(OwnerAuth::SESSION_OWNER_ID);
        $key     = (string) $session->get(OwnerAuth::SESSION_OWNER_KEY);

        if ($ownerId > 0 && service('ownerAuth')->attemptSession($ownerId, $key)) {
            return null;
        }

        if ($ownerId > 0) {
            // stale session (password changed or owner removed)
            $session->destroy();
        }

        if ($request instanceof IncomingRequest && $request->isAJAX()) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['status' => 401, 'error' => 'Unauthorized', 'login' => site_url('login')]);
        }

        if ($request instanceof IncomingRequest && $request->getMethod() === 'GET') {
            $session->setTempdata('web_intended', current_url(), 600);
        }

        return redirect()->to('login');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
