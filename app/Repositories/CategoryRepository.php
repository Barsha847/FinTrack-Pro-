<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use PDO;

/**
 * Class CategoryRepository
 * 
 * Manages SQL access to system and custom user categories.
 */
class CategoryRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * List all categories accessible to the user (system + custom).
     * 
     * @param string $userId
     * @param string|null $type 'income' or 'expense' or null
     * @return array
     */
    public function listActive(string $userId, ?string $type = null): array
    {
        $sql = "
            SELECT * FROM categories 
            WHERE (user_id IS NULL OR user_id = :user_id) 
              AND is_active = TRUE
        ";
        
        $params = [':user_id' => $userId];
        
        if ($type !== null) {
            $sql .= " AND type = :type";
            $params[':type'] = $type;
        }
        
        $sql .= " ORDER BY type ASC, name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find category by ID.
     * 
     * @param string $id
     * @param string $userId
     * @return array|null
     */
    public function findById(string $id, string $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM categories 
            WHERE id = :id 
              AND (user_id IS NULL OR user_id = :user_id)
        ");
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }
}
