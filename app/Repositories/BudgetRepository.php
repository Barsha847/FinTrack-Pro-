<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use PDO;

/**
 * Class BudgetRepository
 * 
 * Manages database access for budget targets.
 */
class BudgetRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Retrieve all budgets for a user.
     */
    public function list(string $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT b.*, c.name AS category_name, c.color AS category_color, c.icon AS category_icon
            FROM budgets b
            LEFT JOIN categories c ON b.category_id = c.id
            WHERE b.user_id = :user_id
            ORDER BY b.budget_year DESC, b.budget_month DESC, c.name ASC
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch a single budget by ID.
     */
    public function findById(string $id, string $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT b.*, c.name AS category_name, c.color AS category_color, c.icon AS category_icon
            FROM budgets b
            LEFT JOIN categories c ON b.category_id = c.id
            WHERE b.id = :id AND b.user_id = :user_id
        ");
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public function findByCategoryAndPeriod(string $userId, string $categoryId, int $month, int $year): ?array
    {
        $stmt = $this->db->prepare("
            SELECT b.*, c.name AS category_name, c.color AS category_color, c.icon AS category_icon 
            FROM budgets b
            LEFT JOIN categories c ON b.category_id = c.id
            WHERE b.user_id = :user_id 
              AND b.category_id = :category_id 
              AND b.budget_month = :month 
              AND b.budget_year = :year
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':category_id' => $categoryId,
            ':month' => $month,
            ':year' => $year
        ]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Insert a new budget record.
     */
    public function create(array $data): array
    {
        $stmt = $this->db->prepare("
            INSERT INTO budgets (
                id, user_id, category_id, amount, budget_month, budget_year, 
                alert_50_sent, alert_75_sent, alert_90_sent, alert_100_sent, created_at, updated_at
            ) VALUES (
                gen_random_uuid(), :user_id, :category_id, :amount, :budget_month, :budget_year, 
                FALSE, FALSE, FALSE, FALSE, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            ) RETURNING *
        ");
        
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':category_id' => $data['category_id'],
            ':amount' => $data['amount'],
            ':budget_month' => $data['budget_month'],
            ':budget_year' => $data['budget_year']
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update an existing budget record.
     */
    public function update(string $id, string $userId, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE budgets 
            SET category_id = :category_id,
                amount = :amount,
                budget_month = :budget_month,
                budget_year = :budget_year,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND user_id = :user_id
        ");
        
        return $stmt->execute([
            ':id' => $id,
            ':user_id' => $userId,
            ':category_id' => $data['category_id'],
            ':amount' => $data['amount'],
            ':budget_month' => $data['budget_month'],
            ':budget_year' => $data['budget_year']
        ]);
    }

    /**
     * Delete a budget record.
     */
    public function delete(string $id, string $userId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM budgets 
            WHERE id = :id AND user_id = :user_id
        ");
        return $stmt->execute([':id' => $id, ':user_id' => $userId]);
    }

    /**
     * Update alert flags.
     */
    public function updateAlertFlags(string $id, string $userId, array $flags): bool
    {
        $stmt = $this->db->prepare("
            UPDATE budgets 
            SET alert_50_sent = :alert_50_sent,
                alert_75_sent = :alert_75_sent,
                alert_90_sent = :alert_90_sent,
                alert_100_sent = :alert_100_sent,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND user_id = :user_id
        ");
        return $stmt->execute([
            ':id' => $id,
            ':user_id' => $userId,
            ':alert_50_sent' => $flags['alert_50_sent'] ? 1 : 0,
            ':alert_75_sent' => $flags['alert_75_sent'] ? 1 : 0,
            ':alert_90_sent' => $flags['alert_90_sent'] ? 1 : 0,
            ':alert_100_sent' => $flags['alert_100_sent'] ? 1 : 0
        ]);
    }
}
