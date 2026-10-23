<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\OwnerAuth;
use App\Models\AuthTokenModel;
use App\Models\OwnerModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Owner authentication for the Flutter app.
 *
 *   POST /auth/login     {phone, password, device?}  -> {token, token_type, expires_in_days, owner}
 *   POST /auth/logout    (bearer)                    -> revokes the current token
 *   GET  /auth/me        (bearer)                    -> owner profile + current token info
 *   PUT  /auth/profile   (bearer) {name, phone?, email?}
 *   PUT  /auth/password  (bearer) {current_password, new_password} -> other devices are logged out
 *   GET  /auth/devices   (bearer)                    -> issued tokens (no secrets)
 *   DELETE /auth/devices/{id} (bearer)               -> revoke one token
 */
class Auth extends BaseController
{
    use ResponseTrait;

    private const LOGIN_ATTEMPTS_PER_MINUTE = 10;

    public function login(): ResponseInterface
    {
        if (ENVIRONMENT !== 'testing') {
            $throttler = service('throttler');

            if ($throttler->check('login_' . md5($this->request->getIPAddress()), self::LOGIN_ATTEMPTS_PER_MINUTE, MINUTE) === false) {
                return $this->fail('Too many login attempts. Try again in a minute.', 429);
            }
        }

        $data  = $this->input();
        $rules = [
            'phone'    => 'required|regex_match[/^[0-9]{10}$/]',
            'password' => 'required|string',
            'device'   => 'permit_empty|string|max_length[100]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $result = service('ownerAuth')->login($data['phone'], $data['password'], $data['device'] ?? null);

        if ($result === null) {
            return $this->failUnauthorized('Phone number or password is incorrect.');
        }

        return $this->respond([
            'token'           => $result['token'],
            'token_type'      => 'Bearer',
            'expires_in_days' => AuthTokenModel::DEFAULT_DAYS,
            'owner'           => $result['owner'],
        ]);
    }

    public function logout(): ResponseInterface
    {
        service('ownerAuth')->logout();

        return $this->respond(['logged_out' => true]);
    }

    public function me(): ResponseInterface
    {
        $auth  = service('ownerAuth');
        $token = $auth->token();

        return $this->respond([
            'owner' => OwnerAuth::publicOwner($auth->owner()),
            'token' => [
                'id'         => (int) $token['id'],
                'name'       => $token['name'],
                'expires_at' => $token['expires_at'],
            ],
        ]);
    }

    public function profile(): ResponseInterface
    {
        $data  = $this->input();
        $rules = [
            'name'  => 'required|string|max_length[100]',
            'phone' => 'permit_empty|regex_match[/^[0-9]{10}$/]',
            'email' => 'permit_empty|valid_email|max_length[190]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $owners = new OwnerModel();
        $update = ['name' => $data['name']];

        if (! empty($data['phone'])) {
            $update['phone'] = $data['phone'];
        }
        if (array_key_exists('email', $data)) {
            $update['email'] = $data['email'] === '' ? null : $data['email'];
        }

        $owners->update(OwnerModel::OWNER_ID, $update);

        return $this->respond(['owner' => OwnerAuth::publicOwner($owners->getOwner())]);
    }

    public function password(): ResponseInterface
    {
        $data  = $this->input();
        $rules = [
            'current_password' => 'required|string',
            'new_password'     => 'required|min_length[8]|max_length[200]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $auth  = service('ownerAuth');
        $owner = $auth->owner();

        if (! password_verify($data['current_password'], (string) $owner['password_hash'])) {
            return $this->failUnauthorized('Current password is incorrect.');
        }

        (new OwnerModel())->update((int) $owner['id'], ['password_hash' => OwnerAuth::hashPassword($data['new_password'])]);
        (new AuthTokenModel())->revokeOthers((int) $owner['id'], (int) $auth->token()['id']);

        return $this->respond(['password_changed' => true, 'other_devices_logged_out' => true]);
    }

    public function devices(): ResponseInterface
    {
        $auth = service('ownerAuth');

        return $this->respond([
            'current_token_id' => (int) $auth->token()['id'],
            'devices'          => (new AuthTokenModel())->listForOwner((int) $auth->owner()['id']),
        ]);
    }

    public function revokeDevice(int $id): ResponseInterface
    {
        $auth   = service('ownerAuth');
        $tokens = new AuthTokenModel();
        $row    = $tokens->where('owner_id', (int) $auth->owner()['id'])->find($id);

        if ($row === null) {
            return $this->failNotFound('Token not found.');
        }

        $tokens->revoke($id);

        return $this->respond(['revoked' => $id]);
    }

    /**
     * JSON body first, then form fields (POST or raw PUT/PATCH).
     *
     * @return array<string, mixed>
     */
    private function input(): array
    {
        $json = $this->request->getJSON(true);

        if (is_array($json)) {
            return $json;
        }

        return array_merge($this->request->getRawInput(), $this->request->getPost());
    }
}
