<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\InvestmentService;
use App\Helpers\ResponseHelper;
use App\Helpers\IpHelper;
use Exception;

/**
 * Class InvestmentController
 * 
 * Maps routing endpoints for investment operations, validates sessions, and translates exceptions to JSON.
 */
class InvestmentController
{
    private InvestmentService $investmentService;

    public function __construct()
    {
        $this->investmentService = new InvestmentService();
    }

    /**
     * Helper to retrieve user ID from active PHP session.
     */
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

    /**
     * Parse incoming JSON request body.
     */
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
     * GET /api/investments
     */
    public function index(): void
    {
        $userId = $this->getAuthenticatedUserId();
        try {
            $data = $this->investmentService->list($userId);
            ResponseHelper::success("Investments retrieved.", $data);
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/investments
     */
    public function store(): void
    {
        $userId = $this->getAuthenticatedUserId();
        $input = $this->getJsonInput();
        $ip = IpHelper::getClientIp();

        try {
            $res = $this->investmentService->create($userId, $input, $ip);
            ResponseHelper::success("Investment logged successfully.", $res, 201);
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    /**
     * GET /api/investments/{id}
     */
    public function show(string $id): void
    {
        $userId = $this->getAuthenticatedUserId();
        try {
            $investment = $this->investmentService->get($id, $userId);
            if (!$investment) {
                ResponseHelper::error("Investment log not found.", 404);
                return;
            }
            ResponseHelper::success("Investment details fetched.", ['investment' => $investment]);
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/investments/{id}
     */
    public function update(string $id): void
    {
        $userId = $this->getAuthenticatedUserId();
        $input = $this->getJsonInput();
        $ip = IpHelper::getClientIp();

        try {
            $res = $this->investmentService->update($id, $userId, $input, $ip);
            if ($res) {
                ResponseHelper::success("Investment details updated.");
            } else {
                ResponseHelper::error("Failed to update investment details.", 500);
            }
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    /**
     * DELETE /api/investments/{id}
     */
    public function destroy(string $id): void
    {
        $userId = $this->getAuthenticatedUserId();
        $ip = IpHelper::getClientIp();

        try {
            $res = $this->investmentService->delete($id, $userId, $ip);
            if ($res) {
                ResponseHelper::success("Investment log deleted successfully.");
            } else {
                ResponseHelper::error("Failed to delete investment log.", 500);
            }
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    /**
     * PATCH /api/investments/{id}/value
     */
    public function updateValuation(string $id): void
    {
        $userId = $this->getAuthenticatedUserId();
        $input = $this->getJsonInput();
        $ip = IpHelper::getClientIp();

        if (!isset($input['current_value'])) {
            ResponseHelper::error("Field 'current_value' is required.", 422);
            return;
        }

        try {
            $currentValue = (float)$input['current_value'];
            $res = $this->investmentService->updateCurrentValue($id, $userId, $currentValue, $ip);
            if ($res) {
                ResponseHelper::success("Investment valuation updated successfully.");
            } else {
                ResponseHelper::error("Failed to update valuation.", 500);
            }
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    /**
     * GET /api/investments/{id}/history
     */
    public function valuationHistory(string $id): void
    {
        $userId = $this->getAuthenticatedUserId();
        try {
            $history = $this->investmentService->getHistory($id, $userId);
            ResponseHelper::success("Investment valuation history fetched.", ['history' => $history]);
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }
}
