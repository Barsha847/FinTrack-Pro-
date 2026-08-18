<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use PDO;

/**
 * Class UserSessionRepository
 * 
 * Synchronizes PHP server session state with database records.
 */
class UserSessionRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Create a user session record in PostgreSQL.
     * 
     * @param string $userId
     * @param string $sessionId
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @param int $expiresInSeconds
     * @return bool
     */
    public function create(string $userId, string $sessionId, ?string $ipAddress, ?string $userAgent, int $expiresInSeconds): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO user_sessions (
                id, user_id, session_id, ip_address, user_agent, last_activity_at, expires_at, created_at
            ) VALUES (
                gen_random_uuid(), :user_id, :session_id, :ip_address, :user_agent, 
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP + (:expires_seconds * INTERVAL '1 second'), 
                CURRENT_TIMESTAMP
            )
            ON CONFLICT (session_id) DO UPDATE SET
                user_id = EXCLUDED.user_id,
                ip_address = EXCLUDED.ip_address,
                user_agent = EXCLUDED.user_agent,
                last_activity_at = CURRENT_TIMESTAMP,
                expires_at = EXCLUDED.expires_at
        ");
        return $stmt->execute([
            ':user_id' => $userId,
            ':session_id' => $sessionId,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent,
            ':expires_seconds' => $expiresInSeconds
        ]);
    }

    /**
     * Touch session to update last activity timestamp.
     * 
     * @param string $sessionId
     * @return bool
     */
    public function updateActivity(string $sessionId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE user_sessions 
            SET last_activity_at = CURRENT_TIMESTAMP 
            WHERE session_id = :session_id
        ");
        return $stmt->execute([':session_id' => $sessionId]);
    }

    /**
     * Delete a session record.
     * 
     * @param string $sessionId
     * @return bool
     */
    public function invalidate(string $sessionId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM user_sessions 
            WHERE session_id = :session_id
        ");
        return $stmt->execute([':session_id' => $sessionId]);
    }

    /**
     * Wipe all sessions for a specific user.
     * 
     * @param string $userId
     * @return bool
     */
    public function invalidateAllForUser(string $userId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM user_sessions 
            WHERE user_id = :user_id
        ");
        return $stmt->execute([':user_id' => $userId]);
    }

    /**
     * Find active session in database.
     * 
     * @param string $sessionId
     * @return array|null
     */
    public function findActive(string $sessionId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM user_sessions 
            WHERE session_id = :session_id AND expires_at > CURRENT_TIMESTAMP
            LIMIT 1
        ");
        $stmt->execute([':session_id' => $sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        return $session ?: null;
    }
}
