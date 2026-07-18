<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use PDO;

/**
 * Class IncomeRepository
 * 
 * Manages database access for income entries.
 */
class IncomeRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Retrieve user incomes, optionally filtered by category or search query.
     */
    public function list(string $userId, ?string $category = null, ?string $search = null): array
    {
        $sql = "
            SELECT i.*, c.name AS category_name, c.color AS category_color, c.icon AS category_icon
            FROM income i
            LEFT JOIN categories c ON i.category_id = c.id
            WHERE i.user_id = :user_id AND i.deleted_at IS NULL
        ";
        
        $params = [':user_id' => $userId];

        if ($category !== null && $category !== 'all') {
            // Support filtering by category ID or name (case-insensitive)
            $sql .= " AND (c.id::text = :category OR LOWER(c.name) = LOWER(:category))";
            $params[':category'] = $category;
        }

        if ($search !== null && trim($search) !== '') {
            $sql .= " AND (i.description ILIKE :search OR i.source ILIKE :search)";
            $params[':search'] = '%' . trim($search) . '%';
        }

        $sql .= " ORDER BY i.income_date DESC, i.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Fetch a single income record by ID.
     */
    public function findById(string $id, string $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT i.*, c.name AS category_name, c.color AS category_color, c.icon AS category_icon
            FROM income i
            LEFT JOIN categories c ON i.category_id = c.id
            WHERE i.id = :id AND i.user_id = :user_id AND i.deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Insert a new income record.
     */
    public function create(array $data): array
    {
        $stmt = $this->db->prepare("
            INSERT INTO income (
                id, user_id, category_id, payment_method_id, amount, income_date, 
                description, source, is_recurring, recurring_frequency, created_at, updated_at
            ) VALUES (
                gen_random_uuid(), :user_id, :category_id, :payment_method_id, :amount, :income_date, 
                :description, :source, :is_recurring, :recurring_frequency, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            ) RETURNING *
        ");
        
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':category_id' => $data['category_id'],
            ':payment_method_id' => $data['payment_method_id'] ?? null,
            ':amount' => $data['amount'],
            ':income_date' => $data['income_date'],
            ':description' => $data['description'] ?? null,
            ':source' => $data['source'] ?? null,
            ':is_recurring' => isset($data['is_recurring']) && $data['is_recurring'] ? 'true' : 'false',
            ':recurring_frequency' => $data['recurring_frequency'] ?? null
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update an existing income record.
     */
    public function update(string $id, string $userId, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE income 
            SET category_id = :category_id,
                payment_method_id = :payment_method_id,
                amount = :amount,
                income_date = :income_date,
                description = :description,
                source = :source,
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
            ':income_date' => $data['income_date'],
            ':description' => $data['description'] ?? null,
            ':source' => $data['source'] ?? null,
            ':is_recurring' => isset($data['is_recurring']) && $data['is_recurring'] ? 'true' : 'false',
            ':recurring_frequency' => $data['recurring_frequency'] ?? null
        ]);
    }

    /**
     * Soft delete an income record.
     */
    public function delete(string $id, string $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE income 
            SET deleted_at = CURRENT_TIMESTAMP, 
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL
        ");
        return $stmt->execute([':id' => $id, ':user_id' => $userId]);
    }
}
