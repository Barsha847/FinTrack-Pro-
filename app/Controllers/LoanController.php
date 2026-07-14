<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\LoanService;
use App\Helpers\ResponseHelper;
use App\Helpers\IpHelper;
use Exception;

/**
 * Class LoanController
 * 
 * Maps HTTP loan endpoints to underlying Amortization and EMI payment trackers.
 */
class LoanController
{
    private LoanService $loanService;

    public function __construct()
    {
        $this->loanService = new LoanService();
    }

    /**
     * Helper to get user ID from session.
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
     * Parse incoming JSON body.
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
     * GET /api/loans
     */
    public function index(): void
    {
        $userId = $this->getAuthenticatedUserId();
        try {
            $data = $this->loanService->listLoans($userId);
            ResponseHelper::success("Loans list retrieved.", $data);
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/loans
     */
    public function store(): void
    {
        $userId = $this->getAuthenticatedUserId();
        $input = $this->getJsonInput();
        $ip = IpHelper::getClientIp();

        // Map UI field names to Service expected names if necessary
        $serviceData = [
            'loan_name' => trim($input['lender'] ?? ''),
            'loan_type' => trim($input['loan_type'] ?? 'Other'),
            'lender_name' => trim($input['lender'] ?? ''),
            'principal_amount' => isset($input['principal']) ? (float)$input['principal'] : 0.0,
            'interest_rate' => isset($input['rate']) ? (float)$input['rate'] : 0.0,
            'emi_amount' => isset($input['emi']) ? (float)$input['emi'] : 0.0,
            'tenure_months' => isset($input['tenor']) ? (int)$input['tenor'] : 0,
            'paid_months' => isset($input['paidMonths']) ? (int)$input['paidMonths'] : 0,
            'start_date' => trim($input['start_date'] ?? date('Y-m-d'))
        ];

        try {
            $res = $this->loanService->createLoan($userId, $serviceData, $ip);
            ResponseHelper::success("Loan account configured successfully.", $res, 201);
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    /**
     * GET /api/loans/{id}
     */
    public function show(string $id): void
    {
        $userId = $this->getAuthenticatedUserId();
        try {
            $loan = $this->loanService->getLoan($id, $userId);
            if (!$loan) {
                ResponseHelper::error("Loan account not found.", 404);
                return;
            }
            ResponseHelper::success("Loan details fetched.", ['loan' => $loan]);
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/loans/{id}
     */
    public function update(string $id): void
    {
        $userId = $this->getAuthenticatedUserId();
        $input = $this->getJsonInput();
        $ip = IpHelper::getClientIp();

        $serviceData = [
            'loan_name' => trim($input['lender'] ?? ''),
            'loan_type' => trim($input['loan_type'] ?? 'Other'),
            'lender_name' => trim($input['lender'] ?? ''),
            'principal_amount' => isset($input['principal']) ? (float)$input['principal'] : 0.0,
            'interest_rate' => isset($input['rate']) ? (float)$input['rate'] : 0.0,
            'emi_amount' => isset($input['emi']) ? (float)$input['emi'] : 0.0,
            'tenure_months' => isset($input['tenor']) ? (int)$input['tenor'] : 0,
            'paid_months' => isset($input['paidMonths']) ? (int)$input['paidMonths'] : 0,
            'start_date' => trim($input['start_date'] ?? date('Y-m-d'))
        ];

        try {
            $res = $this->loanService->updateLoan($id, $userId, $serviceData, $ip);
            if ($res) {
                ResponseHelper::success("Loan account updated successfully.");
            } else {
                ResponseHelper::error("Failed to update loan account.", 500);
            }
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    /**
     * DELETE /api/loans/{id}
     */
    public function destroy(string $id): void
    {
        $userId = $this->getAuthenticatedUserId();
        $ip = IpHelper::getClientIp();

        try {
            $res = $this->loanService->deleteLoan($id, $userId, $ip);
            if ($res) {
                ResponseHelper::success("Loan account record deleted successfully.");
            } else {
                ResponseHelper::error("Failed to delete loan account.", 500);
            }
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    /**
     * GET /api/loans/{loanId}/emis
     */
    public function emis(string $loanId): void
    {
        $userId = $this->getAuthenticatedUserId();
        try {
            $schedule = $this->loanService->getEmiSchedule($loanId, $userId);
            ResponseHelper::success("EMI schedule fetched.", ['schedule' => $schedule]);
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    /**
     * POST /api/loans/{loanId}/emis/{emiId}/pay
     */
    public function payEmi(string $loanId, string $emiId): void
    {
        $userId = $this->getAuthenticatedUserId();
        $ip = IpHelper::getClientIp();

        try {
            $res = $this->loanService->recordEmiPayment($loanId, $emiId, $userId, $ip);
            if ($res) {
                ResponseHelper::success("EMI payment recorded successfully.");
            } else {
                ResponseHelper::error("Failed to record EMI payment.", 500);
            }
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }
}
