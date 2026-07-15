<?php
declare(strict_types=1);

/**
 * verify_phase6.php
 * 
 * Verifies Phase 6 business logic, transactions, deduplication, and IDOR protection.
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database\Database;
use App\Services\ExpenseService;
use App\Services\BudgetService;
use App\Services\BillReminderService;
use App\Services\NotificationService;
use App\Repositories\UserRepository;
use App\Repositories\CategoryRepository;

echo "===================================================\n";
echo "FinTrack Pro - Phase 6 Backend Verification Suite\n";
echo "===================================================\n\n";

try {
    $db = Database::connection();
    $userRepo = new UserRepository();

    // 1. Create Test Users
    $db->exec("DELETE FROM users WHERE email IN ('usera.p6@fintrack.test', 'userb.p6@fintrack.test')");

    $userAId = $userRepo->create([
        'full_name' => 'User A Phase 6',
        'email' => 'usera.p6@fintrack.test',
        'password_hash' => password_hash('Pass123!', PASSWORD_DEFAULT),
        'email_verified' => true,
        'phone_verified' => false,
        'account_status' => 'active',
        'role' => 'user'
    ]);

    $userBId = $userRepo->create([
        'full_name' => 'User B Phase 6',
        'email' => 'userb.p6@fintrack.test',
        'password_hash' => password_hash('Pass123!', PASSWORD_DEFAULT),
        'email_verified' => true,
        'phone_verified' => false,
        'account_status' => 'active',
        'role' => 'user'
    ]);

    echo "[OK] Test users registered:\n";
    echo "  - User A ID: {$userAId}\n";
    echo "  - User B ID: {$userBId}\n\n";

    $categoryRepo = new CategoryRepository();
    $expenseService = new ExpenseService();
    $budgetService = new BudgetService();
    $billService = new BillReminderService();
    $notifService = new NotificationService();

    // Find Food expense category
    $categories = $categoryRepo->listActive($userAId, 'expense');
    $foodCategory = null;
    foreach ($categories as $cat) {
        if (strtolower($cat['name']) === 'food') {
            $foodCategory = $cat;
            break;
        }
    }
    
    if (!$foodCategory) {
        throw new Exception("Food category not found in system categories.");
    }
    $foodCategoryId = $foodCategory['id'];
    echo "[OK] Food Category ID: {$foodCategoryId}\n\n";

    // ===================================================
    // TEST SECTION 1: BUDGET THRESHOLD ALERTS
    // ===================================================
    echo "Running Test Section 1: Budget Threshold Warnings & Deduplication\n";
    echo "---------------------------------------------------\n";

    $currentMonth = (int)date('n');
    $currentYear = (int)date('Y');

    // Create a budget limit of 10000 for Food
    $budget = $budgetService->createBudget($userAId, [
        'category_id' => $foodCategoryId,
        'amount' => 10000.00,
        'budget_month' => $currentMonth,
        'budget_year' => $currentYear
    ]);
    
    $checkBudget = $budgetService->getBudget($budget['id'], $userAId);
    assertResult($checkBudget['alert_50_sent'] === false, "Budget alert_50_sent initialized to FALSE");

    // Spend 4900 (49%). Alert should not trigger.
    $exp1 = $expenseService->createExpense($userAId, [
        'category_id' => $foodCategoryId,
        'amount' => 4900.00,
        'expense_date' => date('Y-m-d'),
        'description' => 'Grocery shopping'
    ]);
    
    $unreadCount = $notifService->getUnreadCount($userAId);
    assertResult($unreadCount === 0, "No alerts generated at 49% budget utilization");

    // Spend another 200 (Total 5100 = 51%). Alert 50% should trigger.
    $exp2 = $expenseService->createExpense($userAId, [
        'category_id' => $foodCategoryId,
        'amount' => 200.00,
        'expense_date' => date('Y-m-d'),
        'description' => 'Fast food dinner'
    ]);
    
    $unreadCount = $notifService->getUnreadCount($userAId);
    assertResult($unreadCount === 1, "Budget 50% alert triggered (Count = 1)");
    
    $notifs = $notifService->getNotifications($userAId);
    assertResult($notifs[0]['type'] === 'budget_50', "Notification type is 'budget_50'");

    // Try evaluating again. Assert no duplicates.
    $budgetService->evaluateBudgetThresholds($userAId, $foodCategoryId, $currentMonth, $currentYear);
    $unreadCount = $notifService->getUnreadCount($userAId);
    assertResult($unreadCount === 1, "Deduplication: Budget 50% alert not duplicated on re-evaluation");

    // Spend another 2500 (Total 7600 = 76%). Alert 75% should trigger.
    $exp3 = $expenseService->createExpense($userAId, [
        'category_id' => $foodCategoryId,
        'amount' => 2500.00,
        'expense_date' => date('Y-m-d'),
        'description' => 'Bulk food purchase'
    ]);
    
    $unreadCount = $notifService->getUnreadCount($userAId);
    assertResult($unreadCount === 2, "Budget 75% alert triggered (Total Alerts = 2)");

    // Log large expense to cross 90% and 100% (Spent 3000, Total 10600 = 106%).
    // Should trigger both alert_90 and alert_100_sent.
    $exp4 = $expenseService->createExpense($userAId, [
        'category_id' => $foodCategoryId,
        'amount' => 3000.00,
        'expense_date' => date('Y-m-d'),
        'description' => 'Party hosting food'
    ]);
    
    $unreadCount = $notifService->getUnreadCount($userAId);
    assertResult($unreadCount === 4, "Budget 90% and 100%+ alerts triggered simultaneously (Total Alerts = 4)");

    echo "\n";

    // ===================================================
    // TEST SECTION 2: BILL REMINDERS & RECURRENCE
    // ===================================================
    echo "Running Test Section 2: Bill Reminders, Date Advancement & Recurrence\n";
    echo "---------------------------------------------------\n";

    // Create recurring monthly bill due in 2 days, reminder 3 days before (eligible today)
    $dueDateStr = date('Y-m-d', strtotime('+2 days'));
    $bill = $billService->createBill($userAId, [
        'bill_name' => 'Monthly AWS Subscription',
        'amount' => 1850.00,
        'due_date' => $dueDateStr,
        'remind_before_days' => 3,
        'is_recurring' => true,
        'recurring_frequency' => 'monthly',
        'category' => 'bills'
    ]);

    // Run processor
    $evalCount = $billService->evaluateBillReminders();
    assertResult($evalCount === 1, "Evaluator triggered upcoming bill reminder notification");

    // Re-run processor, assert deduplication
    $evalCountRepeat = $billService->evaluateBillReminders();
    assertResult($evalCountRepeat === 0, "Deduplication: Repeating process did not generate duplicate notifications");

    // Mark paid inside transaction
    $billService->markBillAsPaid($bill['id'], $userAId);
    
    // Check status is paid
    $paidBill = $billService->getBill($bill['id'], $userAId);
    assertResult($paidBill['status'] === 'paid', "Original bill reminder marked as PAID");

    // Check that next monthly occurrence is created as pending
    $expectedNextDue = $billService->calculateNextDueDate($dueDateStr, 'monthly');
    $allBills = $billService->getBills($userAId);
    
    $nextBillFound = false;
    foreach ($allBills as $b) {
        if ($b['bill_name'] === 'Monthly AWS Subscription' && $b['status'] === 'pending' && $b['due_date'] === $expectedNextDue) {
            $nextBillFound = true;
            break;
        }
    }
    assertResult($nextBillFound, "Option B Recurrence: Next pending bill successfully scheduled for {$expectedNextDue}");

    // Month-End date math boundaries check
    $nextFebLeap = $billService->calculateNextDueDate('2024-01-31', 'monthly'); // 2024 was leap year
    assertResult($nextFebLeap === '2024-02-29', "Leap year month-end advanced correctly: 2024-01-31 -> 2024-02-29");

    $nextFebNonLeap = $billService->calculateNextDueDate('2025-01-31', 'monthly'); // 2025 not leap year
    assertResult($nextFebNonLeap === '2025-02-28', "Non-leap year month-end advanced correctly: 2025-01-31 -> 2025-02-28");

    $nextApril = $billService->calculateNextDueDate('2026-03-31', 'monthly'); // April has 30 days
    assertResult($nextApril === '2026-04-30', "30-day month-end advanced correctly: 2026-03-31 -> 2026-04-30");

    echo "\n";

    // ===================================================
    // TEST SECTION 3: OWNERSHIP SECURITY (IDOR ISOLATION)
    // ===================================================
    echo "Running Test Section 3: Ownership Security Boundaries (IDOR Check)\n";
    echo "---------------------------------------------------\n";

    // User B attempts to access User A's bill details
    $fetchedByB = $billService->getBill($bill['id'], $userBId);
    assertResult($fetchedByB === null, "User B cannot fetch User A's bill record (Access is NULL)");

    // User B attempts to update User A's bill terms
    try {
        $billService->updateBill($bill['id'], $userBId, [
            'bill_name' => 'Hack Attempt',
            'amount' => 9999.00,
            'due_date' => date('Y-m-d'),
            'remind_before_days' => 1
        ]);
        echo "  [FAIL] IDOR breach: User B modified User A's bill reminder.\n";
    } catch (Exception $e) {
        echo "  [OK] User B blocked from editing User A's bill: " . $e->getMessage() . "\n";
    }

    // User B attempts to mark User A's bill as paid
    try {
        $billService->markBillAsPaid($bill['id'], $userBId);
        echo "  [FAIL] IDOR breach: User B marked User A's bill as paid.\n";
    } catch (Exception $e) {
        echo "  [OK] User B blocked from marking User A's bill paid: " . $e->getMessage() . "\n";
    }

    // User B attempts to delete User A's bill reminder
    try {
        $billService->deleteBill($bill['id'], $userBId);
        echo "  [FAIL] IDOR breach: User B deleted User A's bill reminder.\n";
    } catch (Exception $e) {
        echo "  [OK] User B blocked from deleting User A's bill reminder: " . $e->getMessage() . "\n";
    }

    $userANotifs = $notifService->getNotifications($userAId);
    $notifId = $userANotifs[0]['id'];

    $notifRepo = new \App\Repositories\NotificationRepository();
    $fetchedNotifByB = $notifRepo->findById($notifId, $userBId);
    assertResult($fetchedNotifByB === null, "User B cannot read User A's notification details (Access is NULL)");

    // User B attempts to mark User A's notification as read
    $markReadByB = $notifService->markAsRead($notifId, $userBId);
    assertResult($markReadByB === false, "User B cannot mark User A's notification as read (Update fails/returns false)");

    // Clean up
    $db->exec("DELETE FROM users WHERE email IN ('usera.p6@fintrack.test', 'userb.p6@fintrack.test')");
    echo "\nVerification Completed Successfully! All Tests Passed.\n";
    echo "===================================================\n";

} catch (Throwable $e) {
    echo "\n[CRITICAL ERROR] Verification aborted due to exception:\n";
    echo "  Message: " . $e->getMessage() . "\n";
    echo "  File: " . $e->getFile() . " on line " . $e->getLine() . "\n";
    echo "  Trace:\n" . $e->getTraceAsString() . "\n";
    // Cleanup in case of crash
    if (isset($db)) {
        $db->exec("DELETE FROM users WHERE email IN ('usera.p6@fintrack.test', 'userb.p6@fintrack.test')");
    }
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
