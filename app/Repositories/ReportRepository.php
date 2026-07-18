<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use PDO;

/**
 * Class ReportRepository
 * 
 * Performs high-performance SQL aggregations and reporting queries on PostgreSQL.
 */
class ReportRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Helper to apply reusable date filters to SQL queries dynamically.
     * Appends SQL conditions to a base query and populates the params array.
     */
    private function applyDateFilter(string $dateColumn, array $filters, string &$sql, array &$params): void
    {
        $range = $filters['date_range'] ?? 'all';

        switch ($range) {
            case '30days':
                $sql .= " AND {$dateColumn} >= CURRENT_DATE - INTERVAL '30 days'";
                break;
            case '90days':
                $sql .= " AND {$dateColumn} >= CURRENT_DATE - INTERVAL '90 days'";
                break;
            case 'this_month':
                $sql .= " AND EXTRACT(MONTH FROM {$dateColumn}) = EXTRACT(MONTH FROM CURRENT_DATE) 
                          AND EXTRACT(YEAR FROM {$dateColumn}) = EXTRACT(YEAR FROM CURRENT_DATE)";
                break;
            case 'last_month':
                $sql .= " AND {$dateColumn} >= DATE_TRUNC('month', CURRENT_DATE - INTERVAL '1 month') 
                          AND {$dateColumn} < DATE_TRUNC('month', CURRENT_DATE)";
                break;
            case 'this_year':
                $sql .= " AND EXTRACT(YEAR FROM {$dateColumn}) = EXTRACT(YEAR FROM CURRENT_DATE)";
                break;
            case 'custom':
                if (!empty($filters['start_date'])) {
                    $sql .= " AND {$dateColumn} >= :start_date";
                    $params[':start_date'] = $filters['start_date'];
                }
                if (!empty($filters['end_date'])) {
                    $sql .= " AND {$dateColumn} <= :end_date";
                    $params[':end_date'] = $filters['end_date'];
                }
                break;
            case 'all':
            default:
                // No date conditions applied
                break;
        }

        // Apply month/year specific filters if passed explicitly
        if (!empty($filters['month'])) {
            $sql .= " AND EXTRACT(MONTH FROM {$dateColumn}) = :filter_month";
            $params[':filter_month'] = (int)$filters['month'];
        }
        if (!empty($filters['year'])) {
            $sql .= " AND EXTRACT(YEAR FROM {$dateColumn}) = :filter_year";
            $params[':filter_year'] = (int)$filters['year'];
        }
    }

    /**
     * Aggregate total income based on filters.
     */
    public function getIncomeAggregate(string $userId, array $filters = []): float
    {
        $sql = "SELECT COALESCE(SUM(amount), 0.0) FROM income WHERE user_id = :user_id AND deleted_at IS NULL";
        $params = [':user_id' => $userId];

        if (!empty($filters['category_id']) && $filters['category_id'] !== 'all') {
            $sql .= " AND category_id = :category_id";
            $params[':category_id'] = $filters['category_id'];
        }

        $this->applyDateFilter('income_date', $filters, $sql, $params);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Aggregate total expenses based on filters.
     */
    public function getExpenseAggregate(string $userId, array $filters = []): float
    {
        $sql = "SELECT COALESCE(SUM(amount), 0.0) FROM expenses WHERE user_id = :user_id AND deleted_at IS NULL";
        $params = [':user_id' => $userId];

        if (!empty($filters['category_id']) && $filters['category_id'] !== 'all') {
            $sql .= " AND category_id = :category_id";
            $params[':category_id'] = $filters['category_id'];
        }

        $this->applyDateFilter('expense_date', $filters, $sql, $params);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Get aggregate statistics for savings goals.
     */
    public function getSavingsAggregate(string $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COALESCE(SUM(target_amount), 0.0) AS total_target,
                COALESCE(SUM(current_amount), 0.0) AS total_saved
            FROM savings_goals
            WHERE user_id = :user_id AND deleted_at IS NULL
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_target' => 0.0, 'total_saved' => 0.0];
    }

    /**
     * Get aggregate stats for investments.
     */
    public function getInvestmentAggregate(string $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COALESCE(SUM(current_value), 0.0) AS total_current_value,
                COALESCE(SUM(quantity * buy_price), 0.0) AS total_buy_price
            FROM investments
            WHERE user_id = :user_id AND deleted_at IS NULL AND status = 'active'
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_current_value' => 0.0, 'total_buy_price' => 0.0];
    }

    /**
     * Get aggregate stats for loans.
     */
    public function getLoanAggregate(string $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COALESCE(SUM(principal_amount), 0.0) AS total_principal,
                COALESCE(SUM(remaining_amount), 0.0) AS total_remaining,
                COALESCE(SUM(emi_amount), 0.0) AS total_emi
            FROM loans
            WHERE user_id = :user_id AND deleted_at IS NULL AND status = 'active'
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_principal' => 0.0, 'total_remaining' => 0.0, 'total_emi' => 0.0];
    }

    /**
     * Aggregate total paid EMIs.
     */
    public function getPaidEmiAggregate(string $userId): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(amount), 0.0) 
            FROM emi_payments 
            WHERE user_id = :user_id AND status = 'paid'
        ");
        $stmt->execute([':user_id' => $userId]);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Aggregate total pending/due EMIs.
     */
    public function getPendingEmiAggregate(string $userId): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(amount), 0.0) 
            FROM emi_payments 
            WHERE user_id = :user_id AND status = 'pending'
        ");
        $stmt->execute([':user_id' => $userId]);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Aggregate budget limits for a given period.
     */
    public function getBudgetLimitAggregate(string $userId, int $month, int $year): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(amount), 0.0) 
            FROM budgets 
            WHERE user_id = :user_id 
              AND budget_month = :month 
              AND budget_year = :year
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':month' => $month,
            ':year' => $year
        ]);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Get aggregate category breakdowns for chart data.
     */
    public function getCategoryBreakdown(string $userId, string $type, array $filters = []): array
    {
        $table = ($type === 'income') ? 'income' : 'expenses';
        $dateColumn = ($type === 'income') ? 'income_date' : 'expense_date';

        $sql = "
            SELECT 
                c.name AS category_name, 
                c.color AS category_color, 
                c.icon AS category_icon, 
                COALESCE(SUM(t.amount), 0.0) AS total_amount
            FROM {$table} t
            JOIN categories c ON t.category_id = c.id
            WHERE t.user_id = :user_id AND t.deleted_at IS NULL
        ";
        
        $params = [':user_id' => $userId];
        $this->applyDateFilter("t.{$dateColumn}", $filters, $sql, $params);

        $sql .= " GROUP BY c.id, c.name, c.color, c.icon ORDER BY total_amount DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get asset type allocations for investment portfolios.
     */
    public function getInvestmentAllocation(string $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                asset_type, 
                COALESCE(SUM(current_value), 0.0) AS total_value,
                COUNT(*) AS count
            FROM investments
            WHERE user_id = :user_id AND deleted_at IS NULL AND status = 'active'
            GROUP BY asset_type
            ORDER BY total_value DESC
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get monthly Cash Flow trends (grouped by month) for a target year.
     */
    public function getMonthlyCashFlowTrend(string $userId, int $year): array
    {
        // Generates months 1 to 12 and Left Joins aggregated income and expense entries
        $sql = "
            WITH RECURSIVE months AS (
                SELECT 1 AS month
                UNION ALL
                SELECT month + 1 FROM months WHERE month < 12
            ),
            inc_agg AS (
                SELECT 
                    EXTRACT(MONTH FROM income_date) AS month,
                    SUM(amount) AS total_income
                FROM income
                WHERE user_id = :user_id AND deleted_at IS NULL AND EXTRACT(YEAR FROM income_date) = :year
                GROUP BY EXTRACT(MONTH FROM income_date)
            ),
            exp_agg AS (
                SELECT 
                    EXTRACT(MONTH FROM expense_date) AS month,
                    SUM(amount) AS total_expense
                FROM expenses
                WHERE user_id = :user_id AND deleted_at IS NULL AND EXTRACT(YEAR FROM expense_date) = :year
                GROUP BY EXTRACT(MONTH FROM expense_date)
            )
            SELECT 
                m.month,
                COALESCE(i.total_income, 0.0) AS income,
                COALESCE(e.total_expense, 0.0) AS expenses,
                (COALESCE(i.total_income, 0.0) - COALESCE(e.total_expense, 0.0)) AS cash_flow
            FROM months m
            LEFT JOIN inc_agg i ON m.month = i.month
            LEFT JOIN exp_agg e ON m.month = e.month
            ORDER BY m.month ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':year' => $year
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get yearly comparison aggregates.
     */
    public function getYearlyCashFlowTrend(string $userId): array
    {
        $sql = "
            SELECT 
                COALESCE(i.year, e.year) AS year,
                COALESCE(i.total_income, 0.0) AS income,
                COALESCE(e.total_expense, 0.0) AS expenses,
                (COALESCE(i.total_income, 0.0) - COALESCE(e.total_expense, 0.0)) AS cash_flow
            FROM (
                SELECT EXTRACT(YEAR FROM income_date) AS year, SUM(amount) AS total_income
                FROM income
                WHERE user_id = :user_id AND deleted_at IS NULL
                GROUP BY EXTRACT(YEAR FROM income_date)
            ) i
            FULL OUTER JOIN (
                SELECT EXTRACT(YEAR FROM expense_date) AS year, SUM(amount) AS total_expense
                FROM expenses
                WHERE user_id = :user_id AND deleted_at IS NULL
                GROUP BY EXTRACT(YEAR FROM expense_date)
            ) e ON i.year = e.year
            WHERE COALESCE(i.year, e.year) IS NOT NULL
            ORDER BY year ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Returns a paginated, unified detailed transaction log.
     */
    public function getDetailedReportList(string $userId, array $filters = []): array
    {
        // Base sql components
        $incSql = "
            SELECT 
                i.id, 
                'income' AS type, 
                i.amount, 
                i.income_date AS date, 
                i.description, 
                i.source AS subtitle,
                c.name AS category_name,
                c.color AS category_color,
                c.icon AS category_icon
            FROM income i
            LEFT JOIN categories c ON i.category_id = c.id
            WHERE i.user_id = :user_id AND i.deleted_at IS NULL
        ";
        
        $expSql = "
            SELECT 
                e.id, 
                'expense' AS type, 
                e.amount, 
                e.expense_date AS date, 
                e.description, 
                e.merchant AS subtitle,
                c.name AS category_name,
                c.color AS category_color,
                c.icon AS category_icon
            FROM expenses e
            LEFT JOIN categories c ON e.category_id = c.id
            WHERE e.user_id = :user_id AND e.deleted_at IS NULL
        ";

        $incParams = [':user_id' => $userId];
        $expParams = [':user_id' => $userId];

        // Apply filters to income select
        if (!empty($filters['category_id']) && $filters['category_id'] !== 'all') {
            $incSql .= " AND i.category_id = :category_id";
            $incParams[':category_id'] = $filters['category_id'];
        }
        if (!empty($filters['search']) && trim($filters['search']) !== '') {
            $incSql .= " AND (i.description ILIKE :search OR i.source ILIKE :search)";
            $incParams[':search'] = '%' . trim($filters['search']) . '%';
        }
        $this->applyDateFilter('i.income_date', $filters, $incSql, $incParams);

        // Apply filters to expense select
        if (!empty($filters['category_id']) && $filters['category_id'] !== 'all') {
            $expSql .= " AND e.category_id = :category_id";
            $expParams[':category_id'] = $filters['category_id'];
        }
        if (!empty($filters['search']) && trim($filters['search']) !== '') {
            $expSql .= " AND (e.description ILIKE :search OR e.merchant ILIKE :search)";
            $expParams[':search'] = '%' . trim($filters['search']) . '%';
        }
        $this->applyDateFilter('e.expense_date', $filters, $expSql, $expParams);

        // Determine if we are filtering specifically for income or expense type
        $typeFilter = $filters['type'] ?? 'all';
        $unionSql = "";
        $unionParams = [];

        if ($typeFilter === 'income') {
            $unionSql = $incSql;
            $unionParams = $incParams;
        } elseif ($typeFilter === 'expense') {
            $unionSql = $expSql;
            $unionParams = $expParams;
        } else {
            // Merge params and create UNION SQL
            // To execute a PDO prepared statement with two sets of duplicate parameters we must use unique param keys
            $incUniqueParams = [];
            foreach ($incParams as $k => $v) {
                $incUniqueParams[$k . '_inc'] = $v;
            }
            $expUniqueParams = [];
            foreach ($expParams as $k => $v) {
                $expUniqueParams[$k . '_exp'] = $v;
            }

            // Replace param keys in queries
            $incSqlModified = str_replace(array_keys($incParams), array_keys($incUniqueParams), $incSql);
            $expSqlModified = str_replace(array_keys($expParams), array_keys($expUniqueParams), $expSql);

            $unionSql = "({$incSqlModified}) UNION ALL ({$expSqlModified})";
            $unionParams = array_merge($incUniqueParams, $expUniqueParams);
        }

        // Add Sorting
        $unionSql .= " ORDER BY date DESC, id DESC";

        // Pagination
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 100;
        $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;
        
        $unionSql .= " LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($unionSql);
        $stmt->execute($unionParams);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
