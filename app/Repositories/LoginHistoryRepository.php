<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use PDO;

/**
 * Class LoginHistoryRepository
 * 
 * Records connection attempt metrics for audit logging.
 */
class LoginHistoryRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Record a login attempt event (success, failed, blocked).
     * 
     * @param string|null $userId
     * @param string|null $emailAttempted
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @param string $status 'success' | 'failed' | 'blocked'
     * @param string|null $failureReason
     * @return bool
     */
    public function record(?string $userId, ?string $emailAttempted, ?string $ipAddress, ?string $userAgent, string $status, ?string $failureReason = null): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO login_history (
                id, user_id, email_attempted, ip_address, user_agent, status, failure_reason, created_at
            ) VALUES (
                gen_random_uuid(), :user_id, :email_attempted, :ip_address, :user_agent, :status, :failure_reason, CURRENT_TIMESTAMP
            )
        ");
        return $stmt->execute([
            ':user_id' => $userId,
            ':email_attempted' => $emailAttempted,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent,
            ':status' => $status,
            ':failure_reason' => $failureReason
        ]);
    }
}
