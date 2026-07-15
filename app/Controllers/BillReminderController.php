<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\BillReminderService;
use App\Helpers\ResponseHelper;
use App\Helpers\IpHelper;
use Exception;

/**
 * Class BillReminderController
 * 
 * Routes incoming HTTP requests to BillReminderService actions.
 */
class BillReminderController
{
    private BillReminderService $billService;

    public function __construct()
    {
        $this->billService = new BillReminderService();
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
     * GET /api/bills
     */
    public function index(): void
    {
        $userId = $this->getAuthenticatedUserId();
        $status = isset($_GET['status']) ? (string)$_GET['status'] : null;

        try {
            $bills = $this->billService->getBills($userId, $status);
            ResponseHelper::success("Bills list fetched successfully.", ['bills' => $bills]);
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/bills
     */
    public function store(): void
    {
        $userId = $this->getAuthenticatedUserId();
        $input = $this->getJsonInput();
        $ip = IpHelper::getClientIp();

        try {
            $bill = $this->billService->createBill($userId, $input, $ip);
            ResponseHelper::success("Bill reminder configured successfully.", ['bill' => $bill], 201);
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    /**
     * GET /api/bills/upcoming
     */
    public function upcoming(): void
    {
        $userId = $this->getAuthenticatedUserId();

        try {
            $bills = $this->billService->getUpcomingBills($userId);
            ResponseHelper::success("Upcoming bills fetched.", ['bills' => $bills]);
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/bills/overdue
     */
    public function overdue(): void
    {
        $userId = $this->getAuthenticatedUserId();

        try {
            $bills = $this->billService->getOverdueBills($userId);
            ResponseHelper::success("Overdue bills fetched.", ['bills' => $bills]);
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/bills/{id}
     */
    public function show(string $id): void
    {
        $userId = $this->getAuthenticatedUserId();

        try {
            $bill = $this->billService->getBill($id, $userId);
            if (!$bill) {
                ResponseHelper::error("Bill reminder not found.", 404);
                return;
            }
            ResponseHelper::success("Bill reminder details fetched.", ['bill' => $bill]);
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/bills/{id}
     */
    public function update(string $id): void
    {
        $userId = $this->getAuthenticatedUserId();
        $input = $this->getJsonInput();
        $ip = IpHelper::getClientIp();

        try {
            $res = $this->billService->updateBill($id, $userId, $input, $ip);
            if ($res) {
                ResponseHelper::success("Bill reminder updated successfully.");
            } else {
                ResponseHelper::error("Failed to update bill reminder.", 500);
            }
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    /**
     * DELETE /api/bills/{id}
     */
    public function destroy(string $id): void
    {
        $userId = $this->getAuthenticatedUserId();
        $ip = IpHelper::getClientIp();

        try {
            $res = $this->billService->deleteBill($id, $userId, $ip);
            if ($res) {
                ResponseHelper::success("Bill reminder deleted successfully.");
            } else {
                ResponseHelper::error("Failed to delete bill reminder.", 500);
            }
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    /**
     * PUT /api/bills/{id}/paid
     */
    public function paid(string $id): void
    {
        $userId = $this->getAuthenticatedUserId();
        $ip = IpHelper::getClientIp();

        try {
            $res = $this->billService->markBillAsPaid($id, $userId, $ip);
            if ($res) {
                ResponseHelper::success("Bill record marked as paid successfully.");
            } else {
                ResponseHelper::error("Failed to update bill status.", 500);
            }
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }
}
