<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use PDO;

/**
 * Class ActivityLogRepository
 * 
 * Streams user-oriented audit records to activity_logs table.
 */
class ActivityLogRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Record a user operation event.
     * 
     * @param string|null $userId
     * @param string $action
     * @param string|null $module
     * @param string|null $description
     * @param array|null $metadata
     * @param string|null $ipAddress
     * @return bool
     */
    public function record(?string $userId, string $action, ?string $module, ?string $description, ?array $metadata, ?string $ipAddress): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO activity_logs (
                id, user_id, action, module, description, metadata, ip_address, created_at
            ) VALUES (
                gen_random_uuid(), :user_id, :action, :module, :description, :metadata, :ip_address, CURRENT_TIMESTAMP
            )
        ");
        return $stmt->execute([
            ':user_id' => $userId,
            ':action' => $action,
            ':module' => $module,
            ':description' => $description,
            ':metadata' => $metadata ? json_encode($metadata) : null,
            ':ip_address' => $ipAddress
        ]);
    }
}
