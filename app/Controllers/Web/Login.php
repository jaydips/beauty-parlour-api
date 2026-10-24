<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Models\OwnerModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\I18n\Time;

/**
 * Browser sign-in for the owner (same phone + password as the app's API).
 *   GET  /login   form
 *   POST /login   phone + password -> session -> /app
 *   POST /logout  destroys the session
 */
class Login extends WebController
{
    private const ATTEMPTS_PER_MINUTE = 10;

    public function show(): string|RedirectResponse
    {
        if (session()->has('owner_id')) {
            return redirect()->to(site_url('app'));
        }

        return view('web/login', [
            'title'      => 'Login',
            'parlour'    => (string) (new \App\Models\SettingModel())->value('parlour_name', 'Beauty Parlour'),
            'loggedOut'  => $this->request->getGet('out') !== null,
        ]);
    }

    public function attempt(): RedirectResponse
    {
        if (ENVIRONMENT !== 'testing') {
            $throttler = service('throttler');

            if ($throttler->check('weblogin_' . md5($this->request->getIPAddress()), self::ATTEMPTS_PER_MINUTE, MINUTE) === false) {
                return redirect()->back()->withInput()->with('error', 'ઘણા પ્રયાસ થયા. એક મિનિટ પછી ફરી કરો.');
            }
        }

        $rules = [
            'phone'    => 'required|regex_match[/^[0-9]{10}$/]',
            'password' => 'required|string',
        ];

        if (! $this->validateData($this->input(), $rules)) {
            return redirect()->back()->withInput()->with('error', '10 અંકનો mobile નંબર અને password લખો.');
        }

        $phone    = preg_replace('/\D+/', '', (string) $this->request->getPost('phone'));
        $password = (string) $this->request->getPost('password');
        $owners   = new OwnerModel();
        $owner    = $owners->getOwner();

        if ($owner === null || $owner['phone'] !== $phone || empty($owner['password_hash']) || ! password_verify($password, $owner['password_hash'])) {
            return redirect()->back()->withInput()->with('error', 'mobile નંબર અથવા password ખોટો છે.');
        }

        $session = session();
        $session->regenerate(true);
        $session->set([
            'owner_id'   => (int) $owner['id'],
            'owner_name' => $owner['name'],
            'login_at'   => Time::now()->toDateTimeString(),
        ]);
        $owners->update((int) $owner['id'], ['last_login_at' => Time::now()->toDateTimeString()]);

        $intended = (string) ($session->get('intended') ?? '');
        $session->remove('intended');

        if ($intended !== '' && str_starts_with($intended, base_url()) && ! str_contains($intended, '/login')) {
            return redirect()->to($intended);
        }

        return redirect()->to(site_url('app'));
    }

    public function logout(): RedirectResponse
    {
        session()->destroy();

        return redirect()->to(site_url('login') . '?out=1');
    }
}
