<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use PDO;

/**
 * Class NotificationRepository
 * 
 * Manages database access for user alert notifications.
 */
class NotificationRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * List user notifications.
     */
    public function list(string $userId, ?bool $isRead = null, int $limit = 50): array
    {
        $sql = "SELECT * FROM notifications WHERE user_id = :user_id";
        $params = [':user_id' => $userId];

        if ($isRead !== null) {
            $sql .= " AND is_read = :is_read";
            $params[':is_read'] = $isRead ? 1 : 0;
        }

        $sql .= " ORDER BY created_at DESC LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        
        // Bind parameters safely, accounting for limit integer binding
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        if ($isRead !== null) {
            $stmt->bindValue(':is_read', $isRead, PDO::PARAM_BOOL);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find a single notification by ID.
     */
    public function findById(string $id, string $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM notifications 
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Insert a notification safely, avoiding duplicates using ON CONFLICT DO NOTHING.
     */
    public function create(array $data): ?array
    {
        $stmt = $this->db->prepare("
            INSERT INTO notifications (
                id, user_id, type, title, message, is_read, metadata, event_key, created_at
            ) VALUES (
                gen_random_uuid(), :user_id, :type, :title, :message, FALSE, :metadata, :event_key, CURRENT_TIMESTAMP
            )
            ON CONFLICT (user_id, event_key) DO NOTHING
            RETURNING *
        ");
        
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':type' => $data['type'],
            ':title' => $data['title'],
            ':message' => $data['message'],
            ':metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            ':event_key' => $data['event_key'] ?? null
        ]);

        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(string $id, string $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE notifications 
            SET is_read = TRUE, 
                read_at = CURRENT_TIMESTAMP 
            WHERE id = :id AND user_id = :user_id AND is_read = FALSE
        ");
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Mark all notifications for a user as read.
     */
    public function markAllAsRead(string $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE notifications 
            SET is_read = TRUE, 
                read_at = CURRENT_TIMESTAMP 
            WHERE user_id = :user_id AND is_read = FALSE
        ");
        $stmt->execute([':user_id' => $userId]);
        return true; // Return true as execution succeeded (even if zero rows modified)
    }

    /**
     * Delete a notification.
     */
    public function delete(string $id, string $userId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM notifications 
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Count unread notifications.
     */
    public function getUnreadCount(string $userId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM notifications 
            WHERE user_id = :user_id AND is_read = FALSE
        ");
        $stmt->execute([':user_id' => $userId]);
        return (int)$stmt->fetchColumn();
    }
}
