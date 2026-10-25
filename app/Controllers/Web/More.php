<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Libraries\OwnerAuth;
use App\Models\AuthTokenModel;
use App\Models\OwnerModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * "વધુ" tab: menu to the secondary screens, password change and logout.
 *   GET  /app/more
 *   GET  /app/password     POST /app/password {current_password, new_password, confirm}
 */
class More extends WebController
{
    public function index(): string
    {
        return $this->render('web/more', ['title' => lang('App.title_more')], 'more');
    }

    public function password(): string
    {
        return $this->render('web/password', ['title' => lang('App.title_password')], 'more');
    }

    public function updatePassword(): RedirectResponse
    {
        $current = (string) $this->request->getPost('current_password');
        $new     = (string) $this->request->getPost('new_password');
        $confirm = (string) $this->request->getPost('confirm_password');

        if (mb_strlen($new) < 8) {
            return redirect()->back()->with('error', lang('App.password_min'));
        }
        if ($new !== $confirm) {
            return redirect()->back()->with('error', lang('App.password_mismatch'));
        }

        $owners = new OwnerModel();
        $owner  = $owners->getOwner();

        if ($owner === null || ! password_verify($current, (string) $owner['password_hash'])) {
            return redirect()->back()->with('error', lang('App.password_wrong_current'));
        }

        $owners->update((int) $owner['id'], ['password_hash' => OwnerAuth::hashPassword($new)]);
        (new AuthTokenModel())->revokeAllForOwner((int) $owner['id']); // app logins must sign in again

        return redirect()->to(site_url('app/more'))->with('success', lang('App.password_changed'));
    }
}
