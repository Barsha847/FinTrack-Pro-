<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\IncomeRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\ActivityLogRepository;
use Exception;

/**
 * Class IncomeService
 * 
 * Implements business rules and logs changes for income operations.
 */
class IncomeService
{
    private IncomeRepository $incomeRepo;
    private CategoryRepository $categoryRepo;
    private ActivityLogRepository $activityLogRepo;

    public function __construct()
    {
        $this->incomeRepo = new IncomeRepository();
        $this->categoryRepo = new CategoryRepository();
        $this->activityLogRepo = new ActivityLogRepository();
    }

    /**
     * Get list of incomes.
     */
    public function getIncomes(string $userId, ?string $category = null, ?string $search = null): array
    {
        return $this->incomeRepo->list($userId, $category, $search);
    }

    /**
     * Get single income details.
     */
    public function getIncome(string $id, string $userId): ?array
    {
        return $this->incomeRepo->findById($id, $userId);
    }

    /**
     * Create a new income transaction.
     */
    public function createIncome(string $userId, array $data, ?string $ipAddress = null): array
    {
        $this->validate($userId, $data);

        $income = $this->incomeRepo->create([
            'user_id' => $userId,
            'category_id' => $data['category_id'],
            'payment_method_id' => $data['payment_method_id'] ?? null,
            'amount' => (float)$data['amount'],
            'income_date' => $data['income_date'],
            'description' => trim($data['description'] ?? ''),
            'source' => trim($data['source'] ?? ''),
            'is_recurring' => isset($data['is_recurring']) && $data['is_recurring'] ? 1 : 0,
            'recurring_frequency' => $data['recurring_frequency'] ?? null
        ]);

        // Record activity log
        $this->activityLogRepo->record(
            $userId,
            'income_created',
            'Income',
            "Logged income of ₹{$data['amount']} for category ID '{$data['category_id']}'.",
            ['income_id' => $income['id'], 'amount' => $data['amount']],
            $ipAddress
        );

        return $income;
    }

    /**
     * Update an existing income.
     */
    public function updateIncome(string $id, string $userId, array $data, ?string $ipAddress = null): bool
    {
        $existing = $this->incomeRepo->findById($id, $userId);
        if (!$existing) {
            throw new Exception("Income record not found.", 404);
        }

        $this->validate($userId, $data);

        $res = $this->incomeRepo->update($id, $userId, [
            'category_id' => $data['category_id'],
            'payment_method_id' => $data['payment_method_id'] ?? null,
            'amount' => (float)$data['amount'],
            'income_date' => $data['income_date'],
            'description' => trim($data['description'] ?? ''),
            'source' => trim($data['source'] ?? ''),
            'is_recurring' => isset($data['is_recurring']) && $data['is_recurring'] ? 1 : 0,
            'recurring_frequency' => $data['recurring_frequency'] ?? null
        ]);

        if ($res) {
            // Record activity log
            $this->activityLogRepo->record(
                $userId,
                'income_updated',
                'Income',
                "Updated income ID '{$id}' to ₹{$data['amount']}.",
                ['income_id' => $id, 'amount' => $data['amount']],
                $ipAddress
            );
        }

        return $res;
    }

    /**
     * Delete an income record.
     */
    public function deleteIncome(string $id, string $userId, ?string $ipAddress = null): bool
    {
        $existing = $this->incomeRepo->findById($id, $userId);
        if (!$existing) {
            throw new Exception("Income record not found.", 404);
        }

        $res = $this->incomeRepo->delete($id, $userId);

        if ($res) {
            // Record activity log
            $this->activityLogRepo->record(
                $userId,
                'income_deleted',
                'Income',
                "Deleted income '{$existing['description']}' of ₹{$existing['amount']}.",
                ['income_id' => $id],
                $ipAddress
            );
        }

        return $res;
    }

    /**
     * Validate income inputs.
     */
    private function validate(string $userId, array $data): void
    {
        if (!isset($data['category_id']) || empty($data['category_id'])) {
            throw new Exception("Income category is required.", 422);
        }

        $category = $this->categoryRepo->findById($data['category_id'], $userId);
        if (!$category || $category['type'] !== 'income') {
            throw new Exception("Invalid category selection.", 422);
        }

        if (!isset($data['amount']) || (float)$data['amount'] <= 0) {
            throw new Exception("Income amount must be greater than zero.", 422);
        }

        if (!isset($data['income_date']) || empty($data['income_date'])) {
            throw new Exception("Income date is required.", 422);
        }

        // Validate date string
        try {
            new \DateTime($data['income_date']);
        } catch (\Throwable $e) {
            throw new Exception("Invalid income date format.", 422);
        }
    }
}
