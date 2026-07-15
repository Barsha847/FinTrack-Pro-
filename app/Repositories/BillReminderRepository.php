<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use PDO;

/**
 * Class BillReminderRepository
 * 
 * Manages database access for bill tracking.
 */
class BillReminderRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Retrieve all bill reminders for a user.
     */
    public function list(string $userId, ?string $status = null): array
    {
        $sql = "SELECT * FROM bill_reminders WHERE user_id = :user_id";
        $params = [':user_id' => $userId];

        if ($status !== null) {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY due_date ASC, created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch a single bill reminder by ID.
     */
    public function findById(string $id, string $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM bill_reminders 
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Insert a new bill reminder record.
     */
    public function create(array $data): array
    {
        $stmt = $this->db->prepare("
            INSERT INTO bill_reminders (
                id, user_id, bill_name, amount, due_date, remind_before_days, 
                is_recurring, recurring_frequency, status, created_at, updated_at
            ) VALUES (
                gen_random_uuid(), :user_id, :bill_name, :amount, :due_date, :remind_before_days, 
                :is_recurring, :recurring_frequency, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            ) RETURNING *
        ");
        
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':bill_name' => $data['bill_name'],
            ':amount' => $data['amount'] ?? null,
            ':due_date' => $data['due_date'],
            ':remind_before_days' => $data['remind_before_days'] ?? 3,
            ':is_recurring' => isset($data['is_recurring']) && $data['is_recurring'] ? 1 : 0,
            ':recurring_frequency' => $data['recurring_frequency'] ?? null,
            ':status' => $data['status'] ?? 'pending'
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update an existing bill reminder record.
     */
    public function update(string $id, string $userId, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE bill_reminders 
            SET bill_name = :bill_name,
                amount = :amount,
                due_date = :due_date,
                remind_before_days = :remind_before_days,
                is_recurring = :is_recurring,
                recurring_frequency = :recurring_frequency,
                status = :status,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND user_id = :user_id
        ");
        
        return $stmt->execute([
            ':id' => $id,
            ':user_id' => $userId,
            ':bill_name' => $data['bill_name'],
            ':amount' => $data['amount'] ?? null,
            ':due_date' => $data['due_date'],
            ':remind_before_days' => $data['remind_before_days'] ?? 3,
            ':is_recurring' => isset($data['is_recurring']) && $data['is_recurring'] ? 1 : 0,
            ':recurring_frequency' => $data['recurring_frequency'] ?? null,
            ':status' => $data['status'] ?? 'pending'
        ]);
    }

    /**
     * Delete a bill reminder record.
     */
    public function delete(string $id, string $userId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM bill_reminders 
            WHERE id = :id AND user_id = :user_id
        ");
        return $stmt->execute([':id' => $id, ':user_id' => $userId]);
    }

    /**
     * Update bill status.
     */
    public function updateStatus(string $id, string $userId, string $status): bool
    {
        $stmt = $this->db->prepare("
            UPDATE bill_reminders 
            SET status = :status, 
                updated_at = CURRENT_TIMESTAMP 
            WHERE id = :id AND user_id = :user_id
        ");
        return $stmt->execute([':id' => $id, ':user_id' => $userId, ':status' => $status]);
    }

    /**
     * Get active bills that are upcoming (due on or after today, pending).
     */
    public function getUpcoming(string $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM bill_reminders 
            WHERE user_id = :user_id 
              AND status = 'pending' 
              AND due_date >= CURRENT_DATE
            ORDER BY due_date ASC
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get active bills that are overdue (due in the past, pending).
     */
    public function getOverdue(string $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM bill_reminders 
            WHERE user_id = :user_id 
              AND status = 'pending' 
              AND due_date < CURRENT_DATE
            ORDER BY due_date ASC
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * List all bills eligible for alert generation (current_date >= due_date - remind_before_days).
     */
    public function listEligibleReminders(): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM bill_reminders 
            WHERE status = 'pending' 
              AND (due_date - remind_before_days) <= CURRENT_DATE
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
