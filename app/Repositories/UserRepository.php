<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use PDO;

/**
 * Class UserRepository
 * 
 * Manages database interactions for the users table.
 */
class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Find a user by their email case-insensitively.
     * 
     * @param string $email
     * @return array|null
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(:email) AND deleted_at IS NULL");
        $stmt->execute([':email' => trim($email)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    /**
     * Find a user by their unique UUID.
     * 
     * @param string $id
     * @return array|null
     */
    public function findById(string $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    /**
     * Insert a new user into the database and return their UUID.
     * 
     * @param array $data
     * @return string
     */
    public function create(array $data): string
    {
        $stmt = $this->db->prepare("
            INSERT INTO users (
                id, full_name, username, email, phone_number, password_hash, 
                email_verified, phone_verified, account_status, role, profile_image
            ) VALUES (
                gen_random_uuid(), :full_name, :username, :email, :phone_number, :password_hash, 
                :email_verified, :phone_verified, :account_status, :role, :profile_image
            ) RETURNING id
        ");

        $stmt->execute([
            ':full_name' => $data['full_name'],
            ':username' => $data['username'] ?? null,
            ':email' => trim($data['email']),
            ':phone_number' => $data['phone_number'] ?? null,
            ':password_hash' => $data['password_hash'],
            ':email_verified' => (isset($data['email_verified']) && $data['email_verified']) ? 'true' : 'false',
            ':phone_verified' => (isset($data['phone_verified']) && $data['phone_verified']) ? 'true' : 'false',
            ':account_status' => $data['account_status'] ?? 'active',
            ':role' => $data['role'] ?? 'user',
            ':profile_image' => $data['profile_image'] ?? null
        ]);

        return $stmt->fetchColumn();
    }

    /**
     * Dynamically update user attributes.
     * 
     * @param string $id
     * @param array $data
     * @return bool
     */
    public function update(string $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];
        
        foreach ($data as $key => $val) {
            $fields[] = "{$key} = :{$key}";
            // Cast boolean values to string 'true'/'false' for PostgreSQL driver
            if (is_bool($val)) {
                $params[":{$key}"] = $val ? 'true' : 'false';
            } else {
                $params[":{$key}"] = $val;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Increment the failed login attempt counter.
     * 
     * @param string $id
     * @return int New count
     */
    public function incrementFailedLoginAttempts(string $id): int
    {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET failed_login_attempts = failed_login_attempts + 1 
            WHERE id = :id 
            RETURNING failed_login_attempts
        ");
        $stmt->execute([':id' => $id]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Reset login locks and failed counters.
     * 
     * @param string $id
     * @return bool
     */
    public function resetFailedLoginAttempts(string $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET failed_login_attempts = 0, locked_until = NULL 
            WHERE id = :id
        ");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Lock the account for a specific number of minutes.
     * 
     * @param string $id
     * @param int $minutes
     * @return bool
     */
    public function lockAccount(string $id, int $minutes): bool
    {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET locked_until = CURRENT_TIMESTAMP + (:minutes * INTERVAL '1 minute')
            WHERE id = :id
        ");
        return $stmt->execute([
            ':id' => $id,
            ':minutes' => $minutes
        ]);
    }

    /**
     * Set the user's email status to verified.
     * 
     * @param string $id
     * @return bool
     */
    public function markEmailVerified(string $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET email_verified = TRUE 
            WHERE id = :id
        ");
        return $stmt->execute([':id' => $id]);
    }
}
