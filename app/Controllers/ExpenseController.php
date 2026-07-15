<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\ExpenseService;
use App\Helpers\ResponseHelper;
use App\Helpers\IpHelper;
use Exception;

/**
 * Class ExpenseController
 * 
 * Routes incoming HTTP requests to ExpenseService actions.
 */
class ExpenseController
{
    private ExpenseService $expenseService;

    public function __construct()
    {
        $this->expenseService = new ExpenseService();
    }

    private function getAuthenticatedUserId(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            ResponseHelper::error("Unauthenticated request.", 401);
            exit;
        }
        return $userId;
    }

    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        if (empty($input)) {
            return [];
        }
        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            ResponseHelper::error("Malformed JSON payload.", 400);
            exit;
        }
        return $data ?: [];
    }

    /**
     * GET /api/expenses
     */
    public function index(): void
    {
        $userId = $this->getAuthenticatedUserId();
        $category = isset($_GET['category']) ? (string)$_GET['category'] : null;
        $search = isset($_GET['search']) ? (string)$_GET['search'] : null;

        try {
            $expenses = $this->expenseService->getExpenses($userId, $category, $search);
            ResponseHelper::success("Expenses list fetched successfully.", ['expenses' => $expenses]);
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/expenses
     */
    public function store(): void
    {
        $userId = $this->getAuthenticatedUserId();
        $input = $this->getJsonInput();
        $ip = IpHelper::getClientIp();

        try {
            $expense = $this->expenseService->createExpense($userId, $input, $ip);
            ResponseHelper::success("Expense logged successfully.", ['expense' => $expense], 201);
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    /**
     * GET /api/expenses/{id}
     */
    public function show(string $id): void
    {
        $userId = $this->getAuthenticatedUserId();

        try {
            $expense = $this->expenseService->getExpense($id, $userId);
            if (!$expense) {
                ResponseHelper::error("Expense record not found.", 404);
                return;
            }
            ResponseHelper::success("Expense details fetched.", ['expense' => $expense]);
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/expenses/{id}
     */
    public function update(string $id): void
    {
        $userId = $this->getAuthenticatedUserId();
        $input = $this->getJsonInput();
        $ip = IpHelper::getClientIp();

        try {
            $res = $this->expenseService->updateExpense($id, $userId, $input, $ip);
            if ($res) {
                ResponseHelper::success("Expense record updated successfully.");
            } else {
                ResponseHelper::error("Failed to update expense record.", 500);
            }
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    /**
     * DELETE /api/expenses/{id}
     */
    public function destroy(string $id): void
    {
        $userId = $this->getAuthenticatedUserId();
        $ip = IpHelper::getClientIp();

        try {
            $res = $this->expenseService->deleteExpense($id, $userId, $ip);
            if ($res) {
                ResponseHelper::success("Expense record deleted successfully.");
            } else {
                ResponseHelper::error("Failed to delete expense record.", 500);
            }
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }
}
