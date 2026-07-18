<?php
declare(strict_types=1);

/**
 * verify_reporting.php
 * 
 * Integration test script to verify Reports, Analytics, and Income backend modules.
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database\Database;
use App\Services\IncomeService;
use App\Services\ExpenseService;
use App\Services\ReportService;
use App\Repositories\UserRepository;
use App\Repositories\CategoryRepository;

echo "===================================================\n";
echo "FinTrack Pro - Centralized Reporting & Analytics Test\n";
echo "===================================================\n\n";

function assertResult(bool $condition, string $message): void
{
    if ($condition) {
        echo "  [OK] {$message}\n";
    } else {
        echo "  [FAIL] {$message}\n";
        exit(1);
    }
}

try {
    $db = Database::connection();
    $userRepo = new UserRepository();
    $categoryRepo = new CategoryRepository();

    // 1. Setup fresh test user context
    $db->exec("DELETE FROM users WHERE email = 'reporter.test@fintrack.test'");
    
    $testUserId = $userRepo->create([
        'full_name' => 'John Reporter',
        'email' => 'reporter.test@fintrack.test',
        'password_hash' => password_hash('Reporter123!', PASSWORD_DEFAULT),
        'email_verified' => true,
        'phone_verified' => false,
        'account_status' => 'active',
        'role' => 'user'
    ]);

    echo "[OK] Created Test User: {$testUserId}\n\n";

    // 2. Discover default seeded categories
    $incomeCategories = $categoryRepo->listActive($testUserId, 'income');
    $expenseCategories = $categoryRepo->listActive($testUserId, 'expense');

    $salaryCategory = null;
    foreach ($incomeCategories as $cat) {
        if (strtolower($cat['name']) === 'salary') {
            $salaryCategory = $cat;
            break;
        }
    }
    
    $foodCategory = null;
    foreach ($expenseCategories as $cat) {
        if (strtolower($cat['name']) === 'food') {
            $foodCategory = $cat;
            break;
        }
    }

    if (!$salaryCategory || !$foodCategory) {
        throw new Exception("Default system categories are missing. Make sure seeders ran successfully.");
    }

    echo "[OK] Found Category IDs:\n";
    echo "  - Salary: {$salaryCategory['id']}\n";
    echo "  - Food: {$foodCategory['id']}\n\n";

    // Instantiate services
    $incomeService = new IncomeService();
    $expenseService = new ExpenseService();
    $reportService = new ReportService();

    // ===================================================
    // TEST SECTION 1: INCOME REST API SERVICE
    // ===================================================
    echo "1. Verifying Income Service Operations:\n";
    echo "---------------------------------------------------\n";

    $income1 = $incomeService->createIncome($testUserId, [
        'category_id' => $salaryCategory['id'],
        'amount' => 100000.00,
        'income_date' => date('Y-m-d'),
        'description' => 'First salary deposit',
        'source' => 'Acme Corp'
    ]);
    
    assertResult($income1['amount'] == 100000.00, "First income logged successfully");

    $income2 = $incomeService->createIncome($testUserId, [
        'category_id' => $salaryCategory['id'],
        'amount' => 15000.00,
        'income_date' => date('Y-m-d'),
        'description' => 'Freelance project delivery',
        'source' => 'Upwork Contract'
    ]);

    $incomes = $incomeService->getIncomes($testUserId);
    assertResult(count($incomes) === 2, "List retrieves both income entries (Count = 2)");

    // Soft delete one
    $incomeService->deleteIncome($income2['id'], $testUserId);
    $incomesAfterDelete = $incomeService->getIncomes($testUserId);
    assertResult(count($incomesAfterDelete) === 1, "Income record soft deletion works successfully (Count = 1)");

    echo "\n";

    // ===================================================
    // TEST SECTION 2: REPORT AGGREGATIONS & MATHEMATICS
    // ===================================================
    echo "2. Verifying Reporting & Centralized Analytics Engine:\n";
    echo "---------------------------------------------------\n";

    // Create expenses
    $exp1 = $expenseService->createExpense($testUserId, [
        'category_id' => $foodCategory['id'],
        'amount' => 30000.00,
        'expense_date' => date('Y-m-d'),
        'description' => 'Monthly high-end grocer bulk items'
    ]);

    // Insert dummy assets/liabilities direct via DB for analysis testing
    // Add a savings goal
    $goalId = 'c69c6f2a-d9df-4158-b648-7358a9e70110';
    $db->exec("
        INSERT INTO savings_goals (id, user_id, name, target_amount, current_amount, status)
        VALUES ('{$goalId}', '{$testUserId}', 'Emergency Fund Upgrade', 50000.00, 10000.00, 'active')
    ");

    // Add an investment
    $investId = 'e2b34a6e-415b-4393-aa99-7358a9e70220';
    $db->exec("
        INSERT INTO investments (id, user_id, asset_name, asset_type, quantity, buy_price, current_value, buy_date, status)
        VALUES ('{$investId}', '{$testUserId}', 'Apple Stock Equity', 'stock', 10.0, 150.00, 2000.00, '2026-01-15', 'active')
    "); // Cost basis = 1500, value = 2000

    // Add a loan
    $loanId = 'f74c7b8e-415c-4394-bb99-7358a9e70330';
    $db->exec("
        INSERT INTO loans (id, user_id, loan_name, principal_amount, interest_rate, remaining_amount, emi_amount, start_date, status)
        VALUES ('{$loanId}', '{$testUserId}', 'Car Refinance Loan', 25000.00, 6.5, 20000.00, 500.00, '2026-02-01', 'active')
    ");

    // Generate analytical metrics summary
    $summary = $reportService->calculateSummary($testUserId);
    $metrics = $summary['metrics'];
    
    // Assert calculations
    assertResult($metrics['total_income'] == 100000.00, "Calculates Total Income (₹100,000)");
    assertResult($metrics['total_expenses'] == 30000.00, "Calculates Total Expenses (₹30,000)");
    assertResult($metrics['total_savings'] == 10000.00, "Calculates Total Savings (₹10,000)");
    assertResult($metrics['current_investments'] == 2000.00, "Calculates Current Investments (₹2,000)");
    assertResult($metrics['investment_profit'] == 500.00, "Calculates Investment Profit (₹500)");
    assertResult(abs($metrics['investment_roi'] - 33.33) < 0.1, "Calculates Investment ROI (33.33%)");
    assertResult($metrics['outstanding_loans'] == 20000.00, "Calculates Outstanding Loans (₹20,000)");

    // Net Worth Calculation: 
    // Assets = Baseline (420180) + Current Investment (2000) + Savings Saved (10000) + (Income - Expense = 70000) = 502180
    // Liabilities = Loan Remaining (20000)
    // Net Worth = 502180 - 20000 = 482180
    assertResult($metrics['net_worth'] == 482180.00, "Calculates Net Worth correctly based on assets and liabilities (₹482,180)");

    // Savings Rate: (Income - Expense) / Income = (100000 - 30000) / 100000 = 70%
    assertResult($metrics['savings_rate'] == 70.0, "Calculates Savings Rate correctly (70%)");

    // Debt Ratio: Liabilities / Assets = 20000 / 502180 = 3.98%
    assertResult(abs($metrics['debt_ratio'] - 3.98) < 0.1, "Calculates Debt Ratio correctly (~3.98%)");

    // Check financial health grading
    $health = $summary['health_check'];
    assertResult($health['grade'] === 'A+', "Financial Health evaluated as A+ due to high savings rate & low debt");

    // Detailed lists validation
    $details = $reportService->getDetailedReportList($testUserId, ['type' => 'all', 'limit' => 10]);
    assertResult(count($details) === 2, "Detailed reports list fetches both transaction logs unified (Count = 2)");
    assertResult($details[0]['type'] === 'income' || $details[0]['type'] === 'expense', "Unifies transaction items showing type descriptors");

    echo "\n";

    // Clean up test data
    $db->exec("DELETE FROM users WHERE id = '{$testUserId}'");
    echo "[OK] Test database contexts successfully purged.\n";
    echo "===================================================\n";
    echo "All Reporting & Analytics Foundation Tests Passed!\n";
    echo "===================================================\n";

} catch (Throwable $e) {
    echo "\n  [FATAL ERROR] Integration testing encountered failure!\n";
    echo "  Message: " . $e->getMessage() . "\n";
    echo "  File: " . $e->getFile() . " on line " . $e->getLine() . "\n";
    
    // Attempt clean up just in case
    if (isset($db) && isset($testUserId)) {
        $db->exec("DELETE FROM users WHERE id = '{$testUserId}'");
    }
    exit(1);
}
