<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;

/**
 * Bearer tokens issued at login. The raw token never touches the database.
 */
class AuthTokenModel extends Model
{
    public const TOKEN_BYTES   = 32;  // 64 hex characters
    public const DEFAULT_DAYS  = 90;

    protected $table         = 'auth_tokens';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['owner_id', 'name', 'token_hash', 'last_used_at', 'expires_at'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    public static function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    /**
     * Creates a token row and returns the raw token (shown to the client once).
     */
    public function issue(int $ownerId, ?string $deviceName, int $days = self::DEFAULT_DAYS): string
    {
        $raw = bin2hex(random_bytes(self::TOKEN_BYTES));

        $this->insert([
            'owner_id'     => $ownerId,
            'name'         => $deviceName !== null ? mb_substr($deviceName, 0, 100) : null,
            'token_hash'   => self::hash($raw),
            'last_used_at' => Time::now()->toDateTimeString(),
            'expires_at'   => Time::now()->addDays($days)->toDateTimeString(),
        ]);

        return $raw;
    }

    /**
     * Returns the token row for a raw token if it exists and has not expired.
     */
    public function findValid(string $rawToken): ?array
    {
        if (! preg_match('/^[0-9a-f]{64}$/', $rawToken)) {
            return null;
        }

        $row = $this->where('token_hash', self::hash($rawToken))->first();

        if ($row === null) {
            return null;
        }

        if ($row['expires_at'] !== null && $row['expires_at'] < Time::now()->toDateTimeString()) {
            return null;
        }

        return $row;
    }

    public function touch(int $id): void
    {
        $this->update($id, ['last_used_at' => Time::now()->toDateTimeString()]);
    }

    public function revoke(int $id): bool
    {
        return $this->delete($id);
    }

    public function revokeAllForOwner(int $ownerId): bool
    {
        return $this->where('owner_id', $ownerId)->delete();
    }

    /**
     * Revokes every token of the owner except the one currently in use (after a password change).
     */
    public function revokeOthers(int $ownerId, int $keepId): bool
    {
        return $this->where('owner_id', $ownerId)->where('id !=', $keepId)->delete();
    }

    /**
     * Token rows for the owner without hashes (device list for the app).
     */
    public function listForOwner(int $ownerId): array
    {
        return $this->select('id, name, last_used_at, expires_at, created_at')
            ->where('owner_id', $ownerId)
            ->orderBy('last_used_at', 'DESC')
            ->findAll();
    }
}
