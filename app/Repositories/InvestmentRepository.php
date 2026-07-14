<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use PDO;

/**
 * Class InvestmentRepository
 * 
 * Manages database transactions for the investments and investment_history tables.
 */
class InvestmentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Create a new investment.
     * 
     * @param array $data
     * @return string
     */
    public function create(array $data): string
    {
        $stmt = $this->db->prepare("
            INSERT INTO investments (
                id, user_id, asset_name, asset_type, quantity, buy_price, current_value, buy_date, notes, status
            ) VALUES (
                gen_random_uuid(), :user_id, :asset_name, :asset_type, :quantity, :buy_price, :current_value, :buy_date, :notes, :status
            ) RETURNING id
        ");

        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':asset_name' => $data['asset_name'],
            ':asset_type' => $data['asset_type'],
            ':quantity' => $data['quantity'],
            ':buy_price' => $data['buy_price'],
            ':current_value' => $data['current_value'],
            ':buy_date' => $data['buy_date'],
            ':notes' => $data['notes'] ?? null,
            ':status' => $data['status'] ?? 'active'
        ]);

        return $stmt->fetchColumn();
    }

    /**
     * List all investments for a user.
     * 
     * @param string $userId
     * @return array
     */
    public function list(string $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM investments 
            WHERE user_id = :user_id AND deleted_at IS NULL 
            ORDER BY created_at DESC
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Find a single investment by ID and user ID.
     * 
     * @param string $id
     * @param string $userId
     * @return array|null
     */
    public function findById(string $id, string $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM investments 
            WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':id' => $id,
            ':user_id' => $userId
        ]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Update an investment.
     * 
     * @param string $id
     * @param string $userId
     * @param array $data
     * @return bool
     */
    public function update(string $id, string $userId, array $data): bool
    {
        $fields = [];
        $params = [
            ':id' => $id,
            ':user_id' => $userId
        ];

        foreach ($data as $key => $val) {
            $fields[] = "{$key} = :{$key}";
            $params[":{$key}"] = $val;
        }

        if (empty($fields)) {
            return false;
        }

        $query = "
            UPDATE investments 
            SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP 
            WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL
        ";

        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }

    /**
     * Delete an investment (soft delete).
     * 
     * @param string $id
     * @param string $userId
     * @return bool
     */
    public function delete(string $id, string $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE investments 
            SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP 
            WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL
        ");
        return $stmt->execute([
            ':id' => $id,
            ':user_id' => $userId
        ]);
    }

    /**
     * Record a historical valuation in investment_history.
     * 
     * @param string $investmentId
     * @param string $userId
     * @param float $value
     * @return string
     */
    public function createHistory(string $investmentId, string $userId, float $value): string
    {
        $stmt = $this->db->prepare("
            INSERT INTO investment_history (
                id, investment_id, user_id, value, recorded_at
            ) VALUES (
                gen_random_uuid(), :investment_id, :user_id, :value, CURRENT_TIMESTAMP
            ) RETURNING id
        ");
        $stmt->execute([
            ':investment_id' => $investmentId,
            ':user_id' => $userId,
            ':value' => $value
        ]);
        return $stmt->fetchColumn();
    }

    /**
     * List historical valuation logs for an investment.
     * 
     * @param string $investmentId
     * @param string $userId
     * @return array
     */
    public function listHistory(string $investmentId, string $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM investment_history 
            WHERE investment_id = :investment_id AND user_id = :user_id 
            ORDER BY recorded_at DESC
        ");
        $stmt->execute([
            ':investment_id' => $investmentId,
            ':user_id' => $userId
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
