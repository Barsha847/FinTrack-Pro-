<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ReportRepository;

/**
 * Class ReportService
 * 
 * Centralizes the Analytics Engine calculations and financial summaries.
 */
class ReportService
{
    private ReportRepository $reportRepo;

    public function __construct()
    {
        $this->reportRepo = new ReportRepository();
    }

    /**
     * Compute comprehensive financial metrics.
     * All values are calculated from the real PostgreSQL database.
     * 
     * @param string $userId
     * @param array $filters
     * @return array
     */
    public function calculateSummary(string $userId, array $filters = []): array
    {
        // 1. Retrieve raw database aggregates
        $totalIncome = $this->reportRepo->getIncomeAggregate($userId, $filters);
        $totalExpense = $this->reportRepo->getExpenseAggregate($userId, $filters);
        
        $savings = $this->reportRepo->getSavingsAggregate($userId);
        $totalSavingsSaved = (float)$savings['total_saved'];
        $totalSavingsTarget = (float)$savings['total_target'];

        $investments = $this->reportRepo->getInvestmentAggregate($userId);
        $totalInvestmentValue = (float)$investments['total_current_value'];
        $totalInvestmentBuyPrice = (float)$investments['total_buy_price'];

        $loans = $this->reportRepo->getLoanAggregate($userId);
        $loanPrincipal = (float)$loans['total_principal'];
        $loanRemaining = (float)$loans['total_remaining'];
        $loanEmi = (float)$loans['total_emi'];

        $paidEmi = $this->reportRepo->getPaidEmiAggregate($userId);
        $pendingEmi = $this->reportRepo->getPendingEmiAggregate($userId);

        // 2. Fetch baseline balance config dynamically (Defaults to 420180.00)
        $baselineBalance = (float)config('constants.analytics.baseline_balance', 420180.00);

        // 3. Investment Profit & ROI calculations
        $investmentProfit = $totalInvestmentValue - $totalInvestmentBuyPrice;
        $investmentRoi = ($totalInvestmentBuyPrice > 0) ? ($investmentProfit / $totalInvestmentBuyPrice) * 100 : 0.0;

        // 4. Net Worth calculation: Assets - Liabilities
        // Assets = baseline + current investments + current savings + (Income - Expense)
        // Liabilities = outstanding loans remaining amount
        $currentAssets = $baselineBalance + $totalInvestmentValue + $totalSavingsSaved + ($totalIncome - $totalExpense);
        $currentLiabilities = $loanRemaining;
        $netWorth = $currentAssets - $currentLiabilities;

        // 5. Budget usage metrics for the current month
        $currentMonth = (int)date('n');
        $currentYear = (int)date('Y');
        $budgetLimit = $this->reportRepo->getBudgetLimitAggregate($userId, $currentMonth, $currentYear);
        
        // Month specific expense for budget usage
        $monthlyExpenseFilter = [
            'date_range' => 'custom',
            'start_date' => date('Y-m-01'),
            'end_date' => date('Y-m-t')
        ];
        $currentMonthExpense = $this->reportRepo->getExpenseAggregate($userId, $monthlyExpenseFilter);
        
        $budgetUsage = ($budgetLimit > 0) ? ($currentMonthExpense / $budgetLimit) * 100 : 0.0;
        $budgetRemaining = max(0.0, $budgetLimit - $currentMonthExpense);

        // 6. Monthly & Yearly Cash Flow trends
        $monthlyCashFlowTrend = $this->reportRepo->getMonthlyCashFlowTrend($userId, $currentYear);
        $yearlyCashFlowTrend = $this->reportRepo->getYearlyCashFlowTrend($userId);

        // Calculate averages
        $activeMonthsSpending = 0;
        $totalMonthsSpending = 0.0;
        $activeMonthsIncome = 0;
        $totalMonthsIncome = 0.0;

        foreach ($monthlyCashFlowTrend as $mTrend) {
            if ($mTrend['expenses'] > 0) {
                $activeMonthsSpending++;
                $totalMonthsSpending += (float)$mTrend['expenses'];
            }
            if ($mTrend['income'] > 0) {
                $activeMonthsIncome++;
                $totalMonthsIncome += (float)$mTrend['income'];
            }
        }

        $avgMonthlySpending = ($activeMonthsSpending > 0) ? ($totalMonthsSpending / $activeMonthsSpending) : 0.0;
        $avgMonthlyIncome = ($activeMonthsIncome > 0) ? ($totalMonthsIncome / $activeMonthsIncome) : 0.0;

        // 7. Savings Rate
        // Savings Rate = ((Income - Expense) / Income) * 100
        $savingsRate = ($totalIncome > 0) ? (($totalIncome - $totalExpense) / $totalIncome) * 100 : 0.0;

        // 8. Debt Ratio
        // Debt Ratio = Liabilities / Assets * 100
        $debtRatio = ($currentAssets > 0) ? ($currentLiabilities / $currentAssets) * 100 : 0.0;

        // 9. Allocation maps and breakdown lists
        $topExpenseCategories = $this->reportRepo->getCategoryBreakdown($userId, 'expense', $filters);
        $highestIncomeCategories = $this->reportRepo->getCategoryBreakdown($userId, 'income', $filters);
        $investmentAllocation = $this->reportRepo->getInvestmentAllocation($userId);

        // 10. Financial Health Grading
        $healthMetrics = $this->evaluateFinancialHealth($savingsRate, $debtRatio, $budgetUsage);

        // Calculate Growth Rates (MoM Growth based on current and previous month trends)
        $previousMonth = ($currentMonth === 1) ? 12 : $currentMonth - 1;
        $prevYear = ($currentMonth === 1) ? $currentYear - 1 : $currentYear;
        
        $currMonthInc = 0.0;
        $prevMonthInc = 0.0;
        foreach ($monthlyCashFlowTrend as $mTrend) {
            if ((int)$mTrend['month'] === $currentMonth) {
                $currMonthInc = (float)$mTrend['income'];
            }
            if ((int)$mTrend['month'] === $previousMonth) {
                $prevMonthInc = (float)$mTrend['income'];
            }
        }
        $monthlyGrowth = ($prevMonthInc > 0) ? (($currMonthInc - $prevMonthInc) / $prevMonthInc) * 100 : 0.0;

        $yearlyGrowth = 0.0;
        if (count($yearlyCashFlowTrend) >= 2) {
            $lastIdx = count($yearlyCashFlowTrend) - 1;
            $currYearInc = (float)$yearlyCashFlowTrend[$lastIdx]['income'];
            $prevYearInc = (float)$yearlyCashFlowTrend[$lastIdx - 1]['income'];
            $yearlyGrowth = ($prevYearInc > 0) ? (($currYearInc - $prevYearInc) / $prevYearInc) * 100 : 0.0;
        }

        return [
            'metrics' => [
                'total_income' => $totalIncome,
                'total_expenses' => $totalExpense,
                'total_savings' => $totalSavingsSaved,
                'total_savings_target' => $totalSavingsTarget,
                'current_investments' => $totalInvestmentValue,
                'investment_profit' => $investmentProfit,
                'investment_roi' => $investmentRoi,
                'outstanding_loans' => $loanRemaining,
                'loan_principal' => $loanPrincipal,
                'loan_emi' => $loanEmi,
                'paid_emis' => $paidEmi,
                'remaining_emis' => $pendingEmi,
                'budget_usage' => $budgetUsage,
                'budget_remaining' => $budgetRemaining,
                'monthly_cash_flow' => $totalIncome - $totalExpense,
                'net_worth' => $netWorth,
                'avg_monthly_spending' => $avgMonthlySpending,
                'avg_monthly_income' => $avgMonthlyIncome,
                'monthly_growth' => $monthlyGrowth,
                'yearly_growth' => $yearlyGrowth,
                'savings_rate' => $savingsRate,
                'debt_ratio' => $debtRatio
            ],
            'health_check' => $healthMetrics,
            'charts' => [
                'top_expense_categories' => $topExpenseCategories,
                'highest_income_categories' => $highestIncomeCategories,
                'investment_allocation' => $investmentAllocation,
                'monthly_cash_flow_trend' => $monthlyCashFlowTrend,
                'yearly_cash_flow_trend' => $yearlyCashFlowTrend
            ]
        ];
    }

    /**
     * Retrieve detailed transactions matching reports filters.
     */
    public function getDetailedReportList(string $userId, array $filters = []): array
    {
        return $this->reportRepo->getDetailedReportList($userId, $filters);
    }

    /**
     * Assess standard financial indexes to calculate dynamic health scores.
     */
    private function evaluateFinancialHealth(float $savingsRate, float $debtRatio, float $budgetUsage): array
    {
        $score = 100;
        $deductions = [];

        // Savings Rate Deductions (Ideal: > 20%)
        if ($savingsRate < 20.0) {
            $penalty = (int)(20.0 - $savingsRate);
            $score -= min(30, $penalty);
            $deductions[] = "Savings rate is below target 20% limit.";
        }

        // Debt Ratio Deductions (Ideal: < 40%)
        if ($debtRatio > 40.0) {
            $penalty = (int)(($debtRatio - 40.0) * 1.5);
            $score -= min(30, $penalty);
            $deductions[] = "Debt load is exceeding 40% threshold.";
        }

        // Budget compliance limit (Ideal: < 100%)
        if ($budgetUsage > 100.0) {
            $score -= 20;
            $deductions[] = "Monthly category expenses exceeded configured budget limits.";
        }

        $score = max(10, $score);

        $grade = 'A+';
        $status = 'Excellent';
        if ($score < 50) {
            $grade = 'D';
            $status = 'Critical Alert';
        } elseif ($score < 70) {
            $grade = 'C';
            $status = 'Fair';
        } elseif ($score < 85) {
            $grade = 'B';
            $status = 'Good';
        } elseif ($score < 95) {
            $grade = 'A';
            $status = 'Very Good';
        }

        return [
            'score' => $score,
            'grade' => $grade,
            'status' => $status,
            'remarks' => $deductions
        ];
    }
}
