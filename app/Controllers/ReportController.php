<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\ReportService;
use App\Helpers\ResponseHelper;
use Exception;

/**
 * Class ReportController
 * 
 * Handles incoming REST requests for reporting and analytics engine endpoints.
 */
class ReportController
{
    private ReportService $reportService;

    public function __construct()
    {
        $this->reportService = new ReportService();
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

    /**
     * GET /api/reports/summary
     * Centralized financial calculations and dashboard-ready health metrics.
     */
    public function summary(): void
    {
        $userId = $this->getAuthenticatedUserId();

        // Safe extraction & whitelist validation of filters
        $filters = [];
        if (isset($_GET['date_range'])) {
            $allowedRanges = ['all', '30days', '90days', 'this_month', 'last_month', 'this_year', 'custom'];
            $range = (string)$_GET['date_range'];
            $filters['date_range'] = in_array($range, $allowedRanges, true) ? $range : 'all';
        }

        if (isset($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['start_date'])) {
            $filters['start_date'] = (string)$_GET['start_date'];
        }

        if (isset($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['end_date'])) {
            $filters['end_date'] = (string)$_GET['end_date'];
        }

        if (isset($_GET['category_id']) && (string)$_GET['category_id'] !== '') {
            $filters['category_id'] = (string)$_GET['category_id'];
        }

        try {
            $reportData = $this->reportService->calculateSummary($userId, $filters);
            ResponseHelper::success("Report summary generated successfully.", $reportData);
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/reports/details
     * Unified list of transaction logs matching target parameters.
     */
    public function details(): void
    {
        $userId = $this->getAuthenticatedUserId();

        $filters = [];
        
        // Extract ranges
        if (isset($_GET['date_range'])) {
            $allowedRanges = ['all', '30days', '90days', 'this_month', 'last_month', 'this_year', 'custom'];
            $range = (string)$_GET['date_range'];
            $filters['date_range'] = in_array($range, $allowedRanges, true) ? $range : 'all';
        }

        if (isset($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['start_date'])) {
            $filters['start_date'] = (string)$_GET['start_date'];
        }

        if (isset($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['end_date'])) {
            $filters['end_date'] = (string)$_GET['end_date'];
        }

        if (isset($_GET['category_id']) && (string)$_GET['category_id'] !== '') {
            $filters['category_id'] = (string)$_GET['category_id'];
        }

        if (isset($_GET['search']) && trim((string)$_GET['search']) !== '') {
            $filters['search'] = (string)$_GET['search'];
        }

        if (isset($_GET['type'])) {
            $allowedTypes = ['all', 'income', 'expense'];
            $type = (string)$_GET['type'];
            $filters['type'] = in_array($type, $allowedTypes, true) ? $type : 'all';
        }

        // Limit & offset whitelist bounds
        $filters['limit'] = isset($_GET['limit']) ? max(1, min(1000, (int)$_GET['limit'])) : 100;
        $filters['offset'] = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

        try {
            $detailedList = $this->reportService->getDetailedReportList($userId, $filters);
            ResponseHelper::success("Detailed report records fetched.", ['records' => $detailedList]);
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
