<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use PDO;

/**
 * Class LoanRepository
 * 
 * Coordinates SQL interactions for the loans and emi_payments tables.
 */
class LoanRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Create a new loan.
     */
    public function create(array $data): string
    {
        $stmt = $this->db->prepare("
            INSERT INTO loans (
                id, user_id, loan_name, loan_type, lender_name, principal_amount, 
                interest_rate, remaining_amount, emi_amount, start_date, end_date, 
                next_due_date, status, tenure_months
            ) VALUES (
                gen_random_uuid(), :user_id, :loan_name, :loan_type, :lender_name, :principal_amount, 
                :interest_rate, :remaining_amount, :emi_amount, :start_date, :end_date, 
                :next_due_date, :status, :tenure_months
            ) RETURNING id
        ");

        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':loan_name' => $data['loan_name'],
            ':loan_type' => $data['loan_type'] ?? 'other',
            ':lender_name' => $data['lender_name'] ?? null,
            ':principal_amount' => $data['principal_amount'],
            ':interest_rate' => $data['interest_rate'],
            ':remaining_amount' => $data['remaining_amount'],
            ':emi_amount' => $data['emi_amount'] ?? null,
            ':start_date' => $data['start_date'],
            ':end_date' => $data['end_date'] ?? null,
            ':next_due_date' => $data['next_due_date'] ?? null,
            ':status' => $data['status'] ?? 'active',
            ':tenure_months' => $data['tenure_months']
        ]);

        return $stmt->fetchColumn();
    }

    /**
     * List all loans for a user.
     */
    public function list(string $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM loans 
            WHERE user_id = :user_id AND deleted_at IS NULL 
            ORDER BY created_at DESC
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Find a single loan by ID and user ID.
     */
    public function findById(string $id, string $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM loans 
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
     * Update loan attributes.
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
            UPDATE loans 
            SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP 
            WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL
        ";

        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }

    /**
     * Soft delete a loan.
     */
    public function delete(string $id, string $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE loans 
            SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP 
            WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL
        ");
        return $stmt->execute([
            ':id' => $id,
            ':user_id' => $userId
        ]);
    }

    /**
     * Insert multiple EMI schedule records.
     */
    public function createEmiPayments(array $payments): void
    {
        $query = "
            INSERT INTO emi_payments (
                id, loan_id, user_id, amount, due_date, paid_date, status
            ) VALUES (
                gen_random_uuid(), :loan_id, :user_id, :amount, :due_date, :paid_date, :status
            )
        ";
        $stmt = $this->db->prepare($query);

        foreach ($payments as $pay) {
            $stmt->execute([
                ':loan_id' => $pay['loan_id'],
                ':user_id' => $pay['user_id'],
                ':amount' => $pay['amount'],
                ':due_date' => $pay['due_date'],
                ':paid_date' => $pay['paid_date'] ?? null,
                ':status' => $pay['status'] ?? 'pending'
            ]);
        }
    }

    /**
     * Find a single EMI payment by ID, loan ID, and user ID.
     */
    public function findEmiByIdAndLoan(string $emiId, string $loanId, string $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM emi_payments 
            WHERE id = :id AND loan_id = :loan_id AND user_id = :user_id
        ");
        $stmt->execute([
            ':id' => $emiId,
            ':loan_id' => $loanId,
            ':user_id' => $userId
        ]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * List all EMIs for a loan.
     */
    public function listEmisForLoan(string $loanId, string $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM emi_payments 
            WHERE loan_id = :loan_id AND user_id = :user_id 
            ORDER BY due_date ASC
        ");
        $stmt->execute([
            ':loan_id' => $loanId,
            ':user_id' => $userId
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Update an EMI payment state.
     */
    public function updateEmi(string $emiId, string $loanId, string $userId, array $data): bool
    {
        $fields = [];
        $params = [
            ':id' => $emiId,
            ':loan_id' => $loanId,
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
            UPDATE emi_payments 
            SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP 
            WHERE id = :id AND loan_id = :loan_id AND user_id = :user_id
        ";

        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }
}
