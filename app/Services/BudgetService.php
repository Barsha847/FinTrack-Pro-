<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\BudgetRepository;
use App\Repositories\ExpenseRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\ActivityLogRepository;
use App\Database\Database;
use Exception;

/**
 * Class BudgetService
 * 
 * Computes category budget usages and triggers threshold notifications.
 */
class BudgetService
{
    private BudgetRepository $budgetRepo;
    private ExpenseRepository $expenseRepo;
    private NotificationRepository $notificationRepo;
    private CategoryRepository $categoryRepo;
    private ActivityLogRepository $activityLogRepo;

    public function __construct()
    {
        $this->budgetRepo = new BudgetRepository();
        $this->expenseRepo = new ExpenseRepository();
        $this->notificationRepo = new NotificationRepository();
        $this->categoryRepo = new CategoryRepository();
        $this->activityLogRepo = new ActivityLogRepository();
    }

    /**
     * Get budgets.
     */
    public function getBudgets(string $userId): array
    {
        $budgets = $this->budgetRepo->list($userId);
        
        // Enrich budgets with actual spending details
        foreach ($budgets as &$b) {
            $spent = $this->expenseRepo->sumByCategoryAndPeriod($userId, $b['category_id'], (int)$b['budget_month'], (int)$b['budget_year']);
            $b['spent'] = round($spent, 2);
            $limit = (float)$b['amount'];
            $b['progress_percent'] = $limit > 0 ? round(($spent / $limit) * 100.0, 2) : 0.0;
        }

        return $budgets;
    }

    /**
     * Get a single budget.
     */
    public function getBudget(string $id, string $userId): ?array
    {
        $b = $this->budgetRepo->findById($id, $userId);
        if ($b) {
            $spent = $this->expenseRepo->sumByCategoryAndPeriod($userId, $b['category_id'], (int)$b['budget_month'], (int)$b['budget_year']);
            $b['spent'] = round($spent, 2);
            $limit = (float)$b['amount'];
            $b['progress_percent'] = $limit > 0 ? round(($spent / $limit) * 100.0, 2) : 0.0;
        }
        return $b;
    }

    /**
     * Create a new budget.
     */
    public function createBudget(string $userId, array $data, ?string $ipAddress = null): array
    {
        $this->validate($userId, $data);

        // Check uniqueness for category/period
        $existing = $this->budgetRepo->findByCategoryAndPeriod(
            $userId, 
            $data['category_id'], 
            (int)$data['budget_month'], 
            (int)$data['budget_year']
        );
        if ($existing) {
            throw new Exception("A budget limit has already been configured for this category in the selected month.", 409);
        }

        $budget = $this->budgetRepo->create([
            'user_id' => $userId,
            'category_id' => $data['category_id'],
            'amount' => (float)$data['amount'],
            'budget_month' => (int)$data['budget_month'],
            'budget_year' => (int)$data['budget_year']
        ]);

        // Evaluate immediately
        $this->evaluateBudgetThresholds($userId, $data['category_id'], (int)$data['budget_month'], (int)$data['budget_year']);

        $this->activityLogRepo->record(
            $userId,
            'budget_created',
            'Budgets',
            "Set budget limit of ₹{$data['amount']} for category ID '{$data['category_id']}'.",
            ['budget_id' => $budget['id'], 'amount' => $data['amount']],
            $ipAddress
        );

        return $budget;
    }

    /**
     * Update an existing budget.
     */
    public function updateBudget(string $id, string $userId, array $data, ?string $ipAddress = null): bool
    {
        $existing = $this->budgetRepo->findById($id, $userId);
        if (!$existing) {
            throw new Exception("Budget target not found.", 404);
        }

        $this->validate($userId, $data);

        // Check unique constraint if category or period changes
        if ($existing['category_id'] !== $data['category_id'] || 
            (int)$existing['budget_month'] !== (int)$data['budget_month'] || 
            (int)$existing['budget_year'] !== (int)$data['budget_year']) {
            $conflict = $this->budgetRepo->findByCategoryAndPeriod(
                $userId, 
                $data['category_id'], 
                (int)$data['budget_month'], 
                (int)$data['budget_year']
            );
            if ($conflict && $conflict['id'] !== $id) {
                throw new Exception("A budget limit has already been configured for this category in the selected month.", 409);
            }
        }

        $res = $this->budgetRepo->update($id, $userId, [
            'category_id' => $data['category_id'],
            'amount' => (float)$data['amount'],
            'budget_month' => (int)$data['budget_month'],
            'budget_year' => (int)$data['budget_year']
        ]);

        if ($res) {
            // Re-evaluate budget thresholds for the updated period
            $this->evaluateBudgetThresholds($userId, $data['category_id'], (int)$data['budget_month'], (int)$data['budget_year']);

            $this->activityLogRepo->record(
                $userId,
                'budget_updated',
                'Budgets',
                "Modified budget target limit of ID '{$id}' to ₹{$data['amount']}.",
                ['budget_id' => $id, 'amount' => $data['amount']],
                $ipAddress
            );
        }

        return $res;
    }

    /**
     * Delete a budget.
     */
    public function deleteBudget(string $id, string $userId, ?string $ipAddress = null): bool
    {
        $existing = $this->budgetRepo->findById($id, $userId);
        if (!$existing) {
            throw new Exception("Budget target not found.", 404);
        }

        $res = $this->budgetRepo->delete($id, $userId);

        if ($res) {
            $this->activityLogRepo->record(
                $userId,
                'budget_deleted',
                'Budgets',
                "Removed budget target limit of ID '{$id}' for category '{$existing['category_name']}'.",
                ['budget_id' => $id],
                $ipAddress
            );
        }

        return $res;
    }

    /**
     * Evaluates spending against budgets and generates alerts.
     */
    public function evaluateBudgetThresholds(string $userId, string $categoryId, int $month, int $year): void
    {
        $budget = $this->budgetRepo->findByCategoryAndPeriod($userId, $categoryId, $month, $year);
        if (!$budget) {
            return;
        }

        $spent = $this->expenseRepo->sumByCategoryAndPeriod($userId, $categoryId, $month, $year);
        $limit = (float)$budget['amount'];
        if ($limit <= 0.0) {
            return;
        }

        $spentPct = ($spent / $limit) * 100.0;
        
        $alertFlags = [
            'alert_50_sent' => (bool)$budget['alert_50_sent'],
            'alert_75_sent' => (bool)$budget['alert_75_sent'],
            'alert_90_sent' => (bool)$budget['alert_90_sent'],
            'alert_100_sent' => (bool)$budget['alert_100_sent'],
        ];

        $updatedFlags = $alertFlags;
        $notificationsToCreate = [];

        // Check thresholds: 50%, 75%, 90%, 100%+
        if ($spentPct >= 50.0 && !$alertFlags['alert_50_sent']) {
            $updatedFlags['alert_50_sent'] = true;
            $notificationsToCreate[] = [
                'type' => 'budget_50',
                'title' => "Budget Usage: 50% Reached",
                'message' => "You have utilized " . round($spentPct, 1) . "% of your monthly limit for category '{$budget['category_name']}'. Limit: ₹{$limit}, Spent: ₹" . round($spent, 2),
                'event_key' => "budget_alert:{$userId}:{$budget['id']}:{$year}-{$month}:50"
            ];
        }

        if ($spentPct >= 75.0 && !$alertFlags['alert_75_sent']) {
            $updatedFlags['alert_75_sent'] = true;
            $notificationsToCreate[] = [
                'type' => 'budget_75',
                'title' => "Budget Warning: 75% Spent",
                'message' => "Warning: Spending for '{$budget['category_name']}' is at " . round($spentPct, 1) . "% of your limit. Limit: ₹{$limit}, Spent: ₹" . round($spent, 2),
                'event_key' => "budget_alert:{$userId}:{$budget['id']}:{$year}-{$month}:75"
            ];
        }

        if ($spentPct >= 90.0 && !$alertFlags['alert_90_sent']) {
            $updatedFlags['alert_90_sent'] = true;
            $notificationsToCreate[] = [
                'type' => 'budget_90',
                'title' => "Critical Budget: 90% Spent",
                'message' => "Critical: Spending for '{$budget['category_name']}' has reached " . round($spentPct, 1) . "% of your limit.",
                'event_key' => "budget_alert:{$userId}:{$budget['id']}:{$year}-{$month}:90"
            ];
        }

        if ($spentPct >= 100.0 && !$alertFlags['alert_100_sent']) {
            $updatedFlags['alert_100_sent'] = true;
            $notificationsToCreate[] = [
                'type' => 'budget_exceeded',
                'title' => "Budget Exceeded! (100%+)",
                'message' => "Alert: You have exceeded your monthly limit for '{$budget['category_name']}'. Limit: ₹{$limit}, Spent: ₹" . round($spent, 2),
                'event_key' => "budget_alert:{$userId}:{$budget['id']}:{$year}-{$month}:100"
            ];
        }

        // If any flags need updating, save them inside a transaction
        if ($updatedFlags !== $alertFlags) {
            Database::transaction(function() use ($budget, $userId, $updatedFlags, $notificationsToCreate, $spentPct) {
                // Update flags
                $this->budgetRepo->updateAlertFlags($budget['id'], $userId, $updatedFlags);
                
                // Insert notifications
                foreach ($notificationsToCreate as $notif) {
                    $notifData = [
                        'user_id' => $userId,
                        'type' => $notif['type'],
                        'title' => $notif['title'],
                        'message' => $notif['message'],
                        'event_key' => $notif['event_key'],
                        'metadata' => ['budget_id' => $budget['id'], 'spent_pct' => $spentPct]
                    ];
                    $this->notificationRepo->create($notifData);
                }
            });
        }
    }

    /**
     * Validate budget input.
     */
    private function validate(string $userId, array $data): void
    {
        if (!isset($data['category_id']) || empty($data['category_id'])) {
            throw new Exception("Budget category selection is required.", 422);
        }

        $category = $this->categoryRepo->findById($data['category_id'], $userId);
        if (!$category || $category['type'] !== 'expense') {
            throw new Exception("Invalid category selection.", 422);
        }

        if (!isset($data['amount']) || (float)$data['amount'] <= 0) {
            throw new Exception("Budget limit amount must be greater than zero.", 422);
        }

        if (!isset($data['budget_month']) || (int)$data['budget_month'] < 1 || (int)$data['budget_month'] > 12) {
            throw new Exception("Budget month must be between 1 and 12.", 422);
        }

        if (!isset($data['budget_year']) || (int)$data['budget_year'] < 2000 || (int)$data['budget_year'] > 2100) {
            throw new Exception("Budget year must be between 2000 and 2100.", 422);
        }
    }
}
