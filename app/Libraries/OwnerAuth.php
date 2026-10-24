<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\AuthTokenModel;
use App\Models\OwnerModel;
use CodeIgniter\I18n\Time;

/**
 * Request-scoped authentication state for the single owner.
 * Registered as the shared service `ownerAuth` (see Config\Services).
 *
 * Two front doors share it: the JSON API (bearer tokens, attempt()) and the
 * browser UI (PHP session, attemptSession()). Both end with owner() filled.
 */
class OwnerAuth
{
    /** Session keys used by the web UI. */
    public const SESSION_OWNER_ID  = 'owner_id';
    public const SESSION_OWNER_KEY = 'owner_key';

    private ?array $owner = null;
    private ?array $token = null;

    public function __construct(
        private readonly OwnerModel $owners = new OwnerModel(),
        private readonly AuthTokenModel $tokens = new AuthTokenModel(),
    ) {
    }

    /**
     * Authenticates a bearer token. Returns true and remembers owner + token on success.
     */
    public function attempt(string $rawToken): bool
    {
        $token = $this->tokens->findValid($rawToken);

        if ($token === null) {
            return false;
        }

        $owner = $this->owners->find((int) $token['owner_id']);

        if ($owner === null) {
            return false;
        }

        $this->tokens->touch((int) $token['id']);
        $this->owner = $owner;
        $this->token = $token;

        return true;
    }

    /**
     * Verifies phone + password and issues a new token. Returns null on bad credentials.
     *
     * @return array{token: string, owner: array}|null
     */
    public function login(string $phone, string $password, ?string $deviceName): ?array
    {
        $owner = $this->verifyCredentials($phone, $password);

        if ($owner === null) {
            return null;
        }

        $raw = $this->tokens->issue((int) $owner['id'], $deviceName);
        $this->owners->update((int) $owner['id'], ['last_login_at' => Time::now()->toDateTimeString()]);

        return ['token' => $raw, 'owner' => self::publicOwner($this->owners->getOwner())];
    }

    /**
     * Checks phone + password against the owner row. Returns the owner row (with hash) or null.
     */
    public function verifyCredentials(string $phone, string $password): ?array
    {
        $owner = $this->owners->getOwner();

        if ($owner === null || $owner['phone'] !== $phone || empty($owner['password_hash'])) {
            return null;
        }

        if (! password_verify($password, (string) $owner['password_hash'])) {
            return null;
        }

        return $owner;
    }

    /**
     * Browser login: verifies the credentials and stores the owner in the PHP session.
     * Returns the owner row or null on bad credentials.
     */
    public function loginSession(string $phone, string $password): ?array
    {
        $owner = $this->verifyCredentials($phone, $password);

        if ($owner === null) {
            return null;
        }

        $session = session();
        $session->regenerate(true);
        $session->set([
            self::SESSION_OWNER_ID  => (int) $owner['id'],
            self::SESSION_OWNER_KEY => self::sessionKey($owner),
        ]);

        $this->owners->update((int) $owner['id'], ['last_login_at' => Time::now()->toDateTimeString()]);
        $this->owner = $owner;

        return $owner;
    }

    /**
     * Authenticates a browser session (owner id + key stored at login).
     * The key changes with the password hash, so a password change invalidates other browsers.
     */
    public function attemptSession(int $ownerId, string $key): bool
    {
        $owner = $this->owners->find($ownerId);

        if ($owner === null || $key === '' || ! hash_equals(self::sessionKey($owner), $key)) {
            return false;
        }

        $this->owner = $owner;

        return true;
    }

    /**
     * Refreshes the session key after the owner changed the password in this browser.
     */
    public function refreshSessionKey(): void
    {
        $owner = $this->owners->getOwner();

        if ($owner !== null) {
            session()->set(self::SESSION_OWNER_KEY, self::sessionKey($owner));
            $this->owner = $owner;
        }
    }

    public function logoutSession(): void
    {
        $session = session();
        $session->remove([self::SESSION_OWNER_ID, self::SESSION_OWNER_KEY]);
        $session->destroy();
        $this->owner = $this->token = null;
    }

    private static function sessionKey(array $owner): string
    {
        return substr(hash('sha256', 'owner-key:' . (string) ($owner['password_hash'] ?? '')), 0, 32);
    }

    public function logout(): void
    {
        if ($this->token !== null) {
            $this->tokens->revoke((int) $this->token['id']);
        }

        $this->owner = $this->token = null;
    }

    public function check(): bool
    {
        return $this->owner !== null;
    }

    public function owner(): ?array
    {
        return $this->owner;
    }

    public function token(): ?array
    {
        return $this->token;
    }

    /**
     * Owner row without secrets, for API responses.
     */
    public static function publicOwner(?array $owner): ?array
    {
        if ($owner === null) {
            return null;
        }

        unset($owner['password_hash']);

        return $owner;
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
