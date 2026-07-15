<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ExpenseRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\ActivityLogRepository;
use Exception;

/**
 * Class ExpenseService
 * 
 * Implements business rules and logs changes for expense operations.
 */
class ExpenseService
{
    private ExpenseRepository $expenseRepo;
    private CategoryRepository $categoryRepo;
    private ActivityLogRepository $activityLogRepo;
    private BudgetService $budgetService;

    public function __construct()
    {
        $this->expenseRepo = new ExpenseRepository();
        $this->categoryRepo = new CategoryRepository();
        $this->activityLogRepo = new ActivityLogRepository();
        $this->budgetService = new BudgetService();
    }

    /**
     * Get list of expenses.
     */
    public function getExpenses(string $userId, ?string $category = null, ?string $search = null): array
    {
        return $this->expenseRepo->list($userId, $category, $search);
    }

    /**
     * Get single expense details.
     */
    public function getExpense(string $id, string $userId): ?array
    {
        return $this->expenseRepo->findById($id, $userId);
    }

    /**
     * Create a new expense.
     */
    public function createExpense(string $userId, array $data, ?string $ipAddress = null): array
    {
        $this->validate($userId, $data);

        $expense = $this->expenseRepo->create([
            'user_id' => $userId,
            'category_id' => $data['category_id'],
            'payment_method_id' => $data['payment_method_id'] ?? null,
            'amount' => (float)$data['amount'],
            'expense_date' => $data['expense_date'],
            'description' => trim($data['description'] ?? ''),
            'merchant' => trim($data['merchant'] ?? ''),
            'is_recurring' => isset($data['is_recurring']) && $data['is_recurring'] ? 1 : 0,
            'recurring_frequency' => $data['recurring_frequency'] ?? null
        ]);

        // Evaluate budget alert for the target category/period
        $dateObj = new \DateTime($data['expense_date']);
        $month = (int)$dateObj->format('n');
        $year = (int)$dateObj->format('Y');
        
        $this->budgetService->evaluateBudgetThresholds($userId, $data['category_id'], $month, $year);

        // Record activity log
        $this->activityLogRepo->record(
            $userId,
            'expense_created',
            'Expenses',
            "Logged expense of ₹{$data['amount']} for category ID '{$data['category_id']}'.",
            ['expense_id' => $expense['id'], 'amount' => $data['amount']],
            $ipAddress
        );

        return $expense;
    }

    /**
     * Update an existing expense.
     */
    public function updateExpense(string $id, string $userId, array $data, ?string $ipAddress = null): bool
    {
        $existing = $this->expenseRepo->findById($id, $userId);
        if (!$existing) {
            throw new Exception("Expense record not found.", 404);
        }

        $this->validate($userId, $data);

        $res = $this->expenseRepo->update($id, $userId, [
            'category_id' => $data['category_id'],
            'payment_method_id' => $data['payment_method_id'] ?? null,
            'amount' => (float)$data['amount'],
            'expense_date' => $data['expense_date'],
            'description' => trim($data['description'] ?? ''),
            'merchant' => trim($data['merchant'] ?? ''),
            'is_recurring' => isset($data['is_recurring']) && $data['is_recurring'] ? 1 : 0,
            'recurring_frequency' => $data['recurring_frequency'] ?? null
        ]);

        if ($res) {
            // Trigger alerts for both the old category/period and the new category/period
            $oldDateObj = new \DateTime($existing['expense_date']);
            $oldMonth = (int)$oldDateObj->format('n');
            $oldYear = (int)$oldDateObj->format('Y');
            
            $newDateObj = new \DateTime($data['expense_date']);
            $newMonth = (int)$newDateObj->format('n');
            $newYear = (int)$newDateObj->format('Y');

            $this->budgetService->evaluateBudgetThresholds($userId, $existing['category_id'], $oldMonth, $oldYear);
            
            if ($existing['category_id'] !== $data['category_id'] || $oldMonth !== $newMonth || $oldYear !== $newYear) {
                $this->budgetService->evaluateBudgetThresholds($userId, $data['category_id'], $newMonth, $newYear);
            }

            // Record activity log
            $this->activityLogRepo->record(
                $userId,
                'expense_updated',
                'Expenses',
                "Updated expense ID '{$id}' to ₹{$data['amount']}.",
                ['expense_id' => $id, 'amount' => $data['amount']],
                $ipAddress
            );
        }

        return $res;
    }

    /**
     * Delete an expense.
     */
    public function deleteExpense(string $id, string $userId, ?string $ipAddress = null): bool
    {
        $existing = $this->expenseRepo->findById($id, $userId);
        if (!$existing) {
            throw new Exception("Expense record not found.", 404);
        }

        $res = $this->expenseRepo->delete($id, $userId);

        if ($res) {
            // Re-evaluate budget thresholds for the deleted expense category/period
            $dateObj = new \DateTime($existing['expense_date']);
            $month = (int)$dateObj->format('n');
            $year = (int)$dateObj->format('Y');
            
            $this->budgetService->evaluateBudgetThresholds($userId, $existing['category_id'], $month, $year);

            // Record activity log
            $this->activityLogRepo->record(
                $userId,
                'expense_deleted',
                'Expenses',
                "Deleted expense '{$existing['description']}' of ₹{$existing['amount']}.",
                ['expense_id' => $id],
                $ipAddress
            );
        }

        return $res;
    }

    /**
     * Validate expense inputs.
     */
    private function validate(string $userId, array $data): void
    {
        if (!isset($data['category_id']) || empty($data['category_id'])) {
            throw new Exception("Expense category is required.", 422);
        }

        $category = $this->categoryRepo->findById($data['category_id'], $userId);
        if (!$category || $category['type'] !== 'expense') {
            throw new Exception("Invalid category selection.", 422);
        }

        if (!isset($data['amount']) || (float)$data['amount'] <= 0) {
            throw new Exception("Expense amount must be greater than zero.", 422);
        }

        if (!isset($data['expense_date']) || empty($data['expense_date'])) {
            throw new Exception("Expense date is required.", 422);
        }

        // Validate date string
        try {
            new \DateTime($data['expense_date']);
        } catch (\Throwable $e) {
            throw new Exception("Invalid expense date format.", 422);
        }
    }
}
