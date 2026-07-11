<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use PDO;

/**
 * Class EmailVerificationRepository
 * 
 * Manages database tokens for registration email OTP verifications.
 */
class EmailVerificationRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Invalidate any active verification tokens for a specific user.
     * 
     * @param string $userId
     * @return bool
     */
    public function invalidateActiveTokens(string $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE email_verification_tokens 
            SET expires_at = CURRENT_TIMESTAMP - INTERVAL '1 second'
            WHERE user_id = :user_id AND expires_at > CURRENT_TIMESTAMP AND verified_at IS NULL
        ");
        return $stmt->execute([':user_id' => $userId]);
    }

    /**
     * Create a new OTP token challenge.
     * 
     * @param string $userId
     * @param string $tokenHash
     * @param int $expiresInSeconds
     * @return bool
     */
    public function createToken(string $userId, string $tokenHash, int $expiresInSeconds): bool
    {
        // Invalidates previous active tokens
        $this->invalidateActiveTokens($userId);

        $stmt = $this->db->prepare("
            INSERT INTO email_verification_tokens (
                id, user_id, token_hash, expires_at, created_at, attempt_count, last_attempt_at, resend_available_at
            ) VALUES (
                gen_random_uuid(), :user_id, :token_hash, 
                CURRENT_TIMESTAMP + (:expires_seconds * INTERVAL '1 second'), 
                CURRENT_TIMESTAMP, 0, NULL, 
                CURRENT_TIMESTAMP + INTERVAL '60 seconds'
            )
        ");
        return $stmt->execute([
            ':user_id' => $userId,
            ':token_hash' => $tokenHash,
            ':expires_seconds' => $expiresInSeconds
        ]);
    }

    /**
     * Find the latest verification challenge for a user.
     * 
     * @param string $userId
     * @return array|null
     */
    public function findLatestToken(string $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM email_verification_tokens 
            WHERE user_id = :user_id
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $userId]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);
        return $token ?: null;
    }

    /**
     * Increment the incorrect attempt count on a specific token.
     * 
     * @param string $tokenId
     * @return int New attempt count
     */
    public function incrementAttemptCount(string $tokenId): int
    {
        $stmt = $this->db->prepare("
            UPDATE email_verification_tokens 
            SET attempt_count = attempt_count + 1, last_attempt_at = CURRENT_TIMESTAMP 
            WHERE id = :id 
            RETURNING attempt_count
        ");
        $stmt->execute([':id' => $tokenId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Mark a verification token challenge as verified.
     * 
     * @param string $tokenId
     * @return bool
     */
    public function markVerified(string $tokenId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE email_verification_tokens 
            SET verified_at = CURRENT_TIMESTAMP 
            WHERE id = :id
        ");
        return $stmt->execute([':id' => $tokenId]);
    }
}
