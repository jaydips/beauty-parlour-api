<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Libraries\OwnerAuth;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Browser login for the owner (same phone + password as the API, PHP session instead of a token).
 *
 *   GET  /login    form
 *   POST /login    {phone, password}  -> session, redirect to /app (or the page first requested)
 *   GET|POST /logout
 */
class Auth extends WebController
{
    private const LOGIN_ATTEMPTS_PER_MINUTE = 10;

    public function login(): ResponseInterface|string
    {
        if ($this->alreadyLoggedIn()) {
            return redirect()->to(site_url('app'));
        }

        return $this->render('auth/login', [
            'error'     => null,
            'phone'     => '',
            'loggedOut' => $this->request->getGet('out') !== null,
        ], 'લોગિન');
    }

    public function attempt(): ResponseInterface|string
    {
        if (ENVIRONMENT !== 'testing') {
            $throttler = service('throttler');

            if ($throttler->check('login_' . md5($this->request->getIPAddress()), self::LOGIN_ATTEMPTS_PER_MINUTE, MINUTE) === false) {
                return $this->response->setStatusCode(429)->setBody($this->render('auth/login', [
                    'error'     => 'ઘણા બધા પ્રયત્નો થયા. એક મિનિટ પછી ફરી પ્રયત્ન કરો.',
                    'phone'     => '',
                    'loggedOut' => false,
                ], 'લોગિન'));
            }
        }

        $phone    = preg_replace('/\D+/', '', (string) $this->request->getPost('phone')) ?? '';
        $password = (string) $this->request->getPost('password');

        if (! preg_match('/^\d{10}$/', $phone) || $password === '') {
            return $this->render('auth/login', [
                'error'     => '10 અંકનો ફોન નંબર અને પાસવર્ડ દાખલ કરો.',
                'phone'     => $phone,
                'loggedOut' => false,
            ], 'લોગિન');
        }

        $owner = service('ownerAuth')->loginSession($phone, $password);

        if ($owner === null) {
            return $this->response->setStatusCode(401)->setBody($this->render('auth/login', [
                'error'     => 'ફોન નંબર અથવા પાસવર્ડ ખોટો છે.',
                'phone'     => $phone,
                'loggedOut' => false,
            ], 'લોગિન'));
        }

        $session  = session();
        $intended = (string) ($session->getTempdata('web_intended') ?? '');
        $session->removeTempdata('web_intended');

        if ($intended === '' || ! str_starts_with($intended, site_url('app'))) {
            $intended = site_url('app');
        }

        return redirect()->to($intended);
    }

    public function logout(): ResponseInterface
    {
        service('ownerAuth')->logoutSession();

        return redirect()->to(site_url('login?out=1'));
    }

    private function alreadyLoggedIn(): bool
    {
        $session = session();
        $ownerId = (int) $session->get(OwnerAuth::SESSION_OWNER_ID);

        return $ownerId > 0 && service('ownerAuth')->attemptSession($ownerId, (string) $session->get(OwnerAuth::SESSION_OWNER_KEY));
    }
}
