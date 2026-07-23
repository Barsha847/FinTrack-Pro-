<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use PDO;

/**
 * Class RefreshTokenRepository
 * 
 * Manages database tokens for secure token rotation and reuse detection.
 */
class RefreshTokenRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Store a new refresh token hash in database.
     * 
     * @param string $userId
     * @param string $tokenHash SHA-256 hash of raw refresh token
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @param int $expiresInSeconds
     * @return bool
     */
    public function create(string $userId, string $tokenHash, ?string $ipAddress, ?string $userAgent, int $expiresInSeconds): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO refresh_tokens (
                id, user_id, token_hash, ip_address, user_agent, used, expires_at, created_at
            ) VALUES (
                gen_random_uuid(), :user_id, :token_hash, :ip_address, :user_agent, FALSE,
                CURRENT_TIMESTAMP + (:expires_seconds * INTERVAL '1 second'), CURRENT_TIMESTAMP
            )
        ");
        return $stmt->execute([
            ':user_id' => $userId,
            ':token_hash' => $tokenHash,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent,
            ':expires_seconds' => $expiresInSeconds
        ]);
    }

    /**
     * Retrieve a non-expired refresh token by its SHA-256 hash.
     * 
     * @param string $tokenHash
     * @return array|null
     */
    public function findByHash(string $tokenHash): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM refresh_tokens 
            WHERE token_hash = :token_hash AND expires_at > CURRENT_TIMESTAMP
            LIMIT 1
        ");
        $stmt->execute([':token_hash' => $tokenHash]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Mark a refresh token as used.
     * 
     * @param string $tokenHash
     * @return bool
     */
    public function markAsUsed(string $tokenHash): bool
    {
        $stmt = $this->db->prepare("
            UPDATE refresh_tokens 
            SET used = TRUE 
            WHERE token_hash = :token_hash
        ");
        return $stmt->execute([':token_hash' => $tokenHash]);
    }

    /**
     * Delete a specific refresh token from database.
     * 
     * @param string $tokenHash
     * @return bool
     */
    public function delete(string $tokenHash): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM refresh_tokens 
            WHERE token_hash = :token_hash
        ");
        return $stmt->execute([':token_hash' => $tokenHash]);
    }

    /**
     * Delete all refresh tokens for a user (forces global logout).
     * 
     * @param string $userId
     * @return bool
     */
    public function deleteAllForUser(string $userId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM refresh_tokens 
            WHERE user_id = :user_id
        ");
        return $stmt->execute([':user_id' => $userId]);
    }

    /**
     * Prune expired refresh tokens from database.
     * 
     * @return int Number of rows deleted
     */
    public function deleteExpired(): int
    {
        $stmt = $this->db->prepare("
            DELETE FROM refresh_tokens 
            WHERE expires_at <= CURRENT_TIMESTAMP
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
