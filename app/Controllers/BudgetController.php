<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\BudgetService;
use App\Helpers\ResponseHelper;
use App\Helpers\IpHelper;
use Exception;

/**
 * Class BudgetController
 * 
 * Routes incoming HTTP requests to BudgetService actions.
 */
class BudgetController
{
    private BudgetService $budgetService;

    public function __construct()
    {
        $this->budgetService = new BudgetService();
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
     * GET /api/budgets
     */
    public function index(): void
    {
        $userId = $this->getAuthenticatedUserId();

        try {
            $budgets = $this->budgetService->getBudgets($userId);
            ResponseHelper::success("Budgets list fetched successfully.", ['budgets' => $budgets]);
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/budgets
     */
    public function store(): void
    {
        $userId = $this->getAuthenticatedUserId();
        $input = $this->getJsonInput();
        $ip = IpHelper::getClientIp();

        try {
            $budget = $this->budgetService->createBudget($userId, $input, $ip);
            ResponseHelper::success("Budget limit configured successfully.", ['budget' => $budget], 201);
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    /**
     * GET /api/budgets/{id}
     */
    public function show(string $id): void
    {
        $userId = $this->getAuthenticatedUserId();

        try {
            $budget = $this->budgetService->getBudget($id, $userId);
            if (!$budget) {
                ResponseHelper::error("Budget target not found.", 404);
                return;
            }
            ResponseHelper::success("Budget details fetched.", ['budget' => $budget]);
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/budgets/{id}
     */
    public function update(string $id): void
    {
        $userId = $this->getAuthenticatedUserId();
        $input = $this->getJsonInput();
        $ip = IpHelper::getClientIp();

        try {
            $res = $this->budgetService->updateBudget($id, $userId, $input, $ip);
            if ($res) {
                ResponseHelper::success("Budget limit updated successfully.");
            } else {
                ResponseHelper::error("Failed to update budget limit.", 500);
            }
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    /**
     * DELETE /api/budgets/{id}
     */
    public function destroy(string $id): void
    {
        $userId = $this->getAuthenticatedUserId();
        $ip = IpHelper::getClientIp();

        try {
            $res = $this->budgetService->deleteBudget($id, $userId, $ip);
            if ($res) {
                ResponseHelper::success("Budget limit deleted successfully.");
            } else {
                ResponseHelper::error("Failed to delete budget limit.", 500);
            }
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }
}
