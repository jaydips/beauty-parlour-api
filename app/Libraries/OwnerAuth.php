<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\AuthTokenModel;
use App\Models\OwnerModel;
use CodeIgniter\I18n\Time;

/**
 * Request-scoped authentication state for the single owner.
 * Registered as the shared service `ownerAuth` (see Config\Services).
 */
class OwnerAuth
{
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
        $owner = $this->owners->getOwner();

        if ($owner === null || $owner['phone'] !== $phone || empty($owner['password_hash'])) {
            return null;
        }

        if (! password_verify($password, $owner['password_hash'])) {
            return null;
        }

        $raw = $this->tokens->issue((int) $owner['id'], $deviceName);
        $this->owners->update((int) $owner['id'], ['last_login_at' => Time::now()->toDateTimeString()]);

        return ['token' => $raw, 'owner' => self::publicOwner($this->owners->getOwner())];
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
