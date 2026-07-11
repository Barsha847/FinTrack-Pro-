<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use PDO;

/**
 * Class PasswordResetRepository
 * 
 * Manages database tokens for password reset operations.
 */
class PasswordResetRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Invalidate all active reset challenges for a specific user.
     * 
     * @param string $userId
     * @return bool
     */
    public function invalidateActiveTokens(string $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE password_reset_tokens 
            SET expires_at = CURRENT_TIMESTAMP - INTERVAL '1 second'
            WHERE user_id = :user_id AND expires_at > CURRENT_TIMESTAMP AND used_at IS NULL
        ");
        return $stmt->execute([':user_id' => $userId]);
    }

    /**
     * Create a new password reset token entry.
     * 
     * @param string $userId
     * @param string $tokenHash
     * @param int $expiresInSeconds
     * @return bool
     */
    public function createToken(string $userId, string $tokenHash, int $expiresInSeconds): bool
    {
        $this->invalidateActiveTokens($userId);

        $stmt = $this->db->prepare("
            INSERT INTO password_reset_tokens (
                id, user_id, token_hash, expires_at, created_at, used_at
            ) VALUES (
                gen_random_uuid(), :user_id, :token_hash, 
                CURRENT_TIMESTAMP + (:expires_seconds * INTERVAL '1 second'), 
                CURRENT_TIMESTAMP, NULL
            )
        ");
        return $stmt->execute([
            ':user_id' => $userId,
            ':token_hash' => $tokenHash,
            ':expires_seconds' => $expiresInSeconds
        ]);
    }

    /**
     * Find an active, unexpired, and unused password reset token by its hash.
     * 
     * @param string $tokenHash
     * @return array|null
     */
    public function findByTokenHash(string $tokenHash): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM password_reset_tokens 
            WHERE token_hash = :token_hash AND expires_at > CURRENT_TIMESTAMP AND used_at IS NULL
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([':token_hash' => $tokenHash]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);
        return $token ?: null;
    }

    /**
     * Mark a reset token as used.
     * 
     * @param string $tokenId
     * @return bool
     */
    public function markUsed(string $tokenId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE password_reset_tokens 
            SET used_at = CURRENT_TIMESTAMP 
            WHERE id = :id
        ");
        return $stmt->execute([':id' => $tokenId]);
    }
}
