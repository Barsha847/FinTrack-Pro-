<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use PDO;

/**
 * Class ExpenseRepository
 * 
 * Manages database access for expense entries.
 */
class ExpenseRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Retrieve user expenses, optionally filtered by category or search query.
     */
    public function list(string $userId, ?string $category = null, ?string $search = null): array
    {
        $sql = "
            SELECT e.*, c.name AS category_name, c.color AS category_color, c.icon AS category_icon
            FROM expenses e
            LEFT JOIN categories c ON e.category_id = c.id
            WHERE e.user_id = :user_id AND e.deleted_at IS NULL
        ";
        
        $params = [':user_id' => $userId];

        if ($category !== null && $category !== 'all') {
            // Support filtering by category ID or name (case-insensitive)
            $sql .= " AND (c.id::text = :category OR LOWER(c.name) = LOWER(:category))";
            $params[':category'] = $category;
        }

        if ($search !== null && trim($search) !== '') {
            $sql .= " AND (e.description ILIKE :search OR e.merchant ILIKE :search)";
            $params[':search'] = '%' . trim($search) . '%';
        }

        $sql .= " ORDER BY e.expense_date DESC, e.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch a single expense by ID.
     */
    public function findById(string $id, string $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT e.*, c.name AS category_name, c.color AS category_color, c.icon AS category_icon
            FROM expenses e
            LEFT JOIN categories c ON e.category_id = c.id
            WHERE e.id = :id AND e.user_id = :user_id AND e.deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Insert a new expense record.
     */
    public function create(array $data): array
    {
        $stmt = $this->db->prepare("
            INSERT INTO expenses (
                id, user_id, category_id, payment_method_id, amount, expense_date, 
                description, merchant, is_recurring, recurring_frequency, created_at, updated_at
            ) VALUES (
                gen_random_uuid(), :user_id, :category_id, :payment_method_id, :amount, :expense_date, 
                :description, :merchant, :is_recurring, :recurring_frequency, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            ) RETURNING *
        ");
        
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':category_id' => $data['category_id'],
            ':payment_method_id' => $data['payment_method_id'] ?? null,
            ':amount' => $data['amount'],
            ':expense_date' => $data['expense_date'],
            ':description' => $data['description'] ?? null,
            ':merchant' => $data['merchant'] ?? null,
            ':is_recurring' => isset($data['is_recurring']) ? (int)$data['is_recurring'] : 0,
            ':recurring_frequency' => $data['recurring_frequency'] ?? null
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update an existing expense record.
     */
    public function update(string $id, string $userId, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE expenses 
            SET category_id = :category_id,
                payment_method_id = :payment_method_id,
                amount = :amount,
                expense_date = :expense_date,
                description = :description,
                merchant = :merchant,
                is_recurring = :is_recurring,
                recurring_frequency = :recurring_frequency,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL
        ");
        
        return $stmt->execute([
            ':id' => $id,
            ':user_id' => $userId,
            ':category_id' => $data['category_id'],
            ':payment_method_id' => $data['payment_method_id'] ?? null,
            ':amount' => $data['amount'],
            ':expense_date' => $data['expense_date'],
            ':description' => $data['description'] ?? null,
            ':merchant' => $data['merchant'] ?? null,
            ':is_recurring' => isset($data['is_recurring']) ? (int)$data['is_recurring'] : 0,
            ':recurring_frequency' => $data['recurring_frequency'] ?? null
        ]);
    }

    /**
     * Soft delete an expense record.
     */
    public function delete(string $id, string $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE expenses 
            SET deleted_at = CURRENT_TIMESTAMP, 
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL
        ");
        return $stmt->execute([':id' => $id, ':user_id' => $userId]);
    }

    /**
     * Sum the expenses for a specific user, category, month, and year.
     */
    public function sumByCategoryAndPeriod(string $userId, string $categoryId, int $month, int $year): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(amount), 0.0) AS total
            FROM expenses
            WHERE user_id = :user_id 
              AND category_id = :category_id
              AND EXTRACT(MONTH FROM expense_date) = :month
              AND EXTRACT(YEAR FROM expense_date) = :year
              AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':category_id' => $categoryId,
            ':month' => $month,
            ':year' => $year
        ]);
        return (float)$stmt->fetchColumn();
    }
}
