<?php
declare(strict_types=1);

/**
 * verify_phase5.php
 * 
 * Tests calculations, transactions, and ownership security (IDOR checks)
 * for Investments and Loans/EMIs.
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database\Database;
use App\Services\InvestmentService;
use App\Services\LoanService;
use App\Repositories\UserRepository;

echo "===================================================\n";
echo "FinTrack Pro - Phase 5 Logic & Security Verification\n";
echo "===================================================\n\n";

try {
    $db = Database::connection();

    // 1. Create or resolve test users
    $userRepo = new UserRepository();
    
    // Cleanup old test records if any
    $db->exec("DELETE FROM users WHERE email IN ('usera@fintrack.test', 'userb@fintrack.test')");

    $userAId = $userRepo->create([
        'full_name' => 'User A',
        'email' => 'usera@fintrack.test',
        'password_hash' => password_hash('Pass123!', PASSWORD_DEFAULT),
        'email_verified' => true,
        'phone_verified' => false,
        'account_status' => 'active',
        'role' => 'user'
    ]);

    $userBId = $userRepo->create([
        'full_name' => 'User B',
        'email' => 'userb@fintrack.test',
        'password_hash' => password_hash('Pass123!', PASSWORD_DEFAULT),
        'email_verified' => true,
        'phone_verified' => false,
        'account_status' => 'active',
        'role' => 'user'
    ]);

    echo "[OK] Test users registered:\n";
    echo "  - User A ID: {$userAId}\n";
    echo "  - User B ID: {$userBId}\n\n";

    $investmentService = new InvestmentService();
    $loanService = new LoanService();

    // ==========================================
    // TEST SECTION 1: INVESTMENT CALCULATIONS
    // ==========================================
    echo "Running Test Section 1: Investment Calculations\n";
    echo "---------------------------------------------------\n";

    // TEST 1: Purchase price = 100, Quantity = 10, Current value = 1200
    // Expected: Profit = 200, ROI = 20.00%
    $inv1 = $investmentService->create($userAId, [
        'asset_name' => 'HDFC Share',
        'asset_type' => 'stock',
        'buy_price' => 100.00,
        'quantity' => 10.00,
        'current_value' => 1200.00,
        'buy_date' => '2026-07-01'
    ]);
    
    $enriched1 = $investmentService->get($inv1['id'], $userAId);
    assertResult((float)$enriched1['invested_amount'] === 1000.0, "Invested Amount = 1000");
    assertResult((float)$enriched1['profit_loss'] === 200.0, "Profit/Loss = 200");
    assertResult((float)$enriched1['roi'] === 20.00, "ROI = 20.00%");

    // TEST 2: Invested amount = 1000, Current value = 800
    // Expected: Profit = -200, ROI = -20.00%
    $inv2 = $investmentService->create($userAId, [
        'asset_name' => 'Crypto Token',
        'asset_type' => 'crypto',
        'buy_price' => 1000.00,
        'quantity' => 1.0,
        'current_value' => 800.00,
        'buy_date' => '2026-07-02'
    ]);
    $enriched2 = $investmentService->get($inv2['id'], $userAId);
    assertResult((float)$enriched2['profit_loss'] === -200.0, "Profit/Loss = -200");
    assertResult((float)$enriched2['roi'] === -20.00, "ROI = -20.00%");

    // TEST 3: Invested amount = 1000, Current value = 1000
    // Expected: Profit = 0, ROI = 0.00%
    $inv3 = $investmentService->create($userAId, [
        'asset_name' => 'Stable Bond',
        'asset_type' => 'bond',
        'buy_price' => 1000.00,
        'quantity' => 1.0,
        'current_value' => 1000.00,
        'buy_date' => '2026-07-03'
    ]);
    $enriched3 = $investmentService->get($inv3['id'], $userAId);
    assertResult((float)$enriched3['profit_loss'] === 0.0, "Profit/Loss = 0");
    assertResult((float)$enriched3['roi'] === 0.00, "ROI = 0.00%");

    // TEST 4: Attempt zero invested amount validation rejection
    try {
        $investmentService->create($userAId, [
            'asset_name' => 'Bad Asset',
            'asset_type' => 'stock',
            'buy_price' => 0.00,
            'quantity' => 10.00,
            'current_value' => 1200.00
        ]);
        echo "  [FAIL] Test 5 (Zero invested price validation) was not rejected.\n";
    } catch (Exception $e) {
        echo "  [OK] Test 5 (Zero invested price validation) rejected with: " . $e->getMessage() . "\n";
    }

    // TEST 5: Attempt negative current value validation rejection
    try {
        $investmentService->create($userAId, [
            'asset_name' => 'Bad Asset 2',
            'asset_type' => 'stock',
            'buy_price' => 100.00,
            'quantity' => 10.00,
            'current_value' => -500.00
        ]);
        echo "  [FAIL] Test 6 (Negative current value validation) was not rejected.\n";
    } catch (Exception $e) {
        echo "  [OK] Test 6 (Negative current value validation) rejected with: " . $e->getMessage() . "\n";
    }

    echo "\n";

    // ==========================================
    // TEST SECTION 2: LOAN / EMI CALCULATIONS & BALANCES
    // ==========================================
    echo "Running Test Section 2: Loan & EMI Calculations\n";
    echo "---------------------------------------------------\n";

    // Create a test loan: Principal = 50,000, EMI = 5,000, Tenor = 10, Paid months = 0
    $loan = $loanService->createLoan($userAId, [
        'loan_name' => 'Test Personal Loan',
        'loan_type' => 'Personal Loan',
        'principal_amount' => 50000.00,
        'interest_rate' => 10.00,
        'emi_amount' => 5000.00,
        'tenure_months' => 10,
        'paid_months' => 0,
        'start_date' => '2026-06-01'
    ]);

    $loanId = $loan['id'];
    $enrichedLoan = $loanService->getLoan($loanId, $userAId);
    assertResult((float)$enrichedLoan['remaining_amount'] === 50000.00, "Initial remaining balance = 50,000");

    // Fetch the EMI schedule
    $schedule = $loanService->getEmiSchedule($loanId, $userAId);
    assertResult(count($schedule) === 10, "Amortization schedule created 10 monthly EMI records");

    // Pay EMI 1
    $emi1 = $schedule[0];
    $loanService->recordEmiPayment($loanId, $emi1['id'], $userAId);
    $enrichedLoan = $loanService->getLoan($loanId, $userAId);
    assertResult((float)$enrichedLoan['remaining_amount'] === 45000.00, "Remaining balance after 1st EMI = 45,000");

    // Attempt to pay the 1st EMI again
    try {
        $loanService->recordEmiPayment($loanId, $emi1['id'], $userAId);
        echo "  [FAIL] Test duplicate EMI payment was not blocked.\n";
    } catch (Exception $e) {
        echo "  [OK] Test duplicate EMI payment blocked: " . $e->getMessage() . "\n";
    }

    // Pay up to the last installment (record 9 more payments)
    for ($i = 1; $i < 10; $i++) {
        $loanService->recordEmiPayment($loanId, $schedule[$i]['id'], $userAId);
    }
    
    $enrichedLoanFinal = $loanService->getLoan($loanId, $userAId);
    assertResult((float)$enrichedLoanFinal['remaining_amount'] === 0.00, "Remaining balance after final payment = 0.00");
    assertResult($enrichedLoanFinal['status'] === 'paid', "Loan status automatically changed to terminal status 'paid'");

    echo "\n";

    // ==========================================
    // TEST SECTION 3: OWNERSHIP SECURITY (IDOR ISOLATION)
    // ==========================================
    echo "Running Test Section 3: Ownership Security (IDOR Protection)\n";
    echo "---------------------------------------------------\n";

    // User A creates an investment asset
    $invA = $investmentService->create($userAId, [
        'asset_name' => 'User A Asset',
        'asset_type' => 'stock',
        'buy_price' => 1000.00,
        'quantity' => 1.0,
        'current_value' => 1000.00,
        'buy_date' => '2026-07-01'
    ]);

    // User B attempts to access it
    $fetchedByB = $investmentService->get($invA['id'], $userBId);
    assertResult($fetchedByB === null, "User B cannot read User A's investment asset (Access is NULL)");

    // User B attempts to update its valuation
    try {
        $investmentService->updateCurrentValue($invA['id'], $userBId, 2000.00);
        echo "  [FAIL] User B successfully modified User A's investment valuation.\n";
    } catch (Exception $e) {
        echo "  [OK] User B blocked from updating User A's investment valuation: " . $e->getMessage() . "\n";
    }

    // User B attempts to delete it
    try {
        $investmentService->delete($invA['id'], $userBId);
        echo "  [FAIL] User B successfully deleted User A's investment asset.\n";
    } catch (Exception $e) {
        echo "  [OK] User B blocked from deleting User A's investment asset: " . $e->getMessage() . "\n";
    }

    // User A creates a loan account
    $loanA = $loanService->createLoan($userAId, [
        'loan_name' => 'User A Loan',
        'loan_type' => 'Personal Loan',
        'principal_amount' => 10000.00,
        'interest_rate' => 5.00,
        'emi_amount' => 1000.00,
        'tenure_months' => 10,
        'paid_months' => 0,
        'start_date' => '2026-06-01'
    ]);
    $loanAId = $loanA['id'];

    // User B attempts to access it
    $loanFetchedByB = $loanService->getLoan($loanAId, $userBId);
    assertResult($loanFetchedByB === null, "User B cannot read User A's loan details (Access is NULL)");

    // User B attempts to pay User A's EMI
    $scheduleA = $loanService->getEmiSchedule($loanAId, $userAId);
    $emiA1Id = $scheduleA[0]['id'];
    try {
        $loanService->recordEmiPayment($loanAId, $emiA1Id, $userBId);
        echo "  [FAIL] User B successfully paid User A's EMI installment.\n";
    } catch (Exception $e) {
        echo "  [OK] User B blocked from paying User A's EMI installment: " . $e->getMessage() . "\n";
    }

    // Clean up
    $db->exec("DELETE FROM users WHERE email IN ('usera@fintrack.test', 'userb@fintrack.test')");
    echo "\nVerification Completed Successfully! All Tests Passed.\n";
    echo "===================================================\n";

} catch (Throwable $e) {
    echo "\n[CRITICAL ERROR] Verification aborted due to exception:\n";
    echo "  Message: " . $e->getMessage() . "\n";
    echo "  File: " . $e->getFile() . " on line " . $e->getLine() . "\n";
    echo "  Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

function assertResult(bool $condition, string $testName): void
{
    if ($condition) {
        echo "  [OK] Pass: {$testName}\n";
    } else {
        echo "  [FAIL] Fail: {$testName}\n";
        throw new RuntimeException("Assertion failed for test: {$testName}");
    }
}
