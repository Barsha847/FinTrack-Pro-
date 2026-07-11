<?php
declare(strict_types=1);

/**
 * FinTrack Pro - Schema Verification Utility
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database\Database;

echo "===================================================\n";
echo "FinTrack Pro - Database Schema Verification Framework\n";
echo "===================================================\n\n";

try {
    $db = Database::connection();

    // 1. Check Database Version
    $stmtVersion = $db->query("SELECT version()");
    $version = $stmtVersion->fetchColumn();
    echo "[OK] Connected to: " . $version . "\n\n";

    // 2. Verify UUID Generation
    $stmtUuid = $db->query("SELECT gen_random_uuid()");
    $uuid = $stmtUuid->fetchColumn();
    echo "[OK] UUID Generation Working (Sample UUID: {$uuid})\n\n";

    // 3. Expected Tables List
    $expectedTables = [
        'schema_migrations',
        'users',
        'categories',
        'payment_methods',
        'income',
        'expenses',
        'receipts',
        'budgets',
        'savings_goals',
        'savings_contributions',
        'investments',
        'investment_history',
        'loans',
        'emi_payments',
        'bill_reminders',
        'notifications',
        'user_sessions',
        'password_reset_tokens',
        'email_verification_tokens',
        'login_history',
        'activity_logs',
        'audit_logs',
        'user_settings'
    ];

    echo "Verifying Table Existence:\n";
    echo "---------------------------------------------------\n";
    $missingTables = [];
    foreach ($expectedTables as $table) {
        $stmtTable = $db->prepare("
            SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public' 
                AND table_name = :table
            )
        ");
        $stmtTable->execute([':table' => $table]);
        $exists = $stmtTable->fetchColumn();

        if ($exists) {
            echo "  [OK] Table exists: {$table}\n";
        } else {
            echo "  [FAIL] Table missing: {$table}\n";
            $missingTables[] = $table;
        }
    }
    echo "---------------------------------------------------\n\n";

    // 4. Verify Triggers exist on tables
    echo "Verifying 'updated_at' Automations (Triggers):\n";
    echo "---------------------------------------------------\n";
    $expectedTriggers = [
        'users' => 'trigger_update_users_updated_at',
        'categories' => 'trigger_update_categories_updated_at',
        'payment_methods' => 'trigger_update_payment_methods_updated_at',
        'income' => 'trigger_update_income_updated_at',
        'expenses' => 'trigger_update_expenses_updated_at',
        'budgets' => 'trigger_update_budgets_updated_at',
        'savings_goals' => 'trigger_update_savings_goals_updated_at',
        'investments' => 'trigger_update_investments_updated_at',
        'loans' => 'trigger_update_loans_updated_at',
        'emi_payments' => 'trigger_update_emi_payments_updated_at',
        'bill_reminders' => 'trigger_update_bill_reminders_updated_at',
        'user_settings' => 'trigger_update_user_settings_updated_at',
    ];

    $missingTriggersCount = 0;
    foreach ($expectedTriggers as $table => $trigger) {
        $stmtTrigger = $db->prepare("
            SELECT EXISTS (
                SELECT FROM information_schema.triggers 
                WHERE event_object_table = :table 
                AND trigger_name = :trigger
            )
        ");
        $stmtTrigger->execute([
            ':table' => $table,
            ':trigger' => $trigger
        ]);
        $exists = $stmtTrigger->fetchColumn();

        if ($exists) {
            echo "  [OK] Trigger exists: {$trigger} on {$table}\n";
        } else {
            echo "  [FAIL] Trigger missing: {$trigger} on {$table}\n";
            $missingTriggersCount++;
        }
    }
    echo "---------------------------------------------------\n\n";

    // 5. Verify Migration Tracking
    $stmtMig = $db->query("SELECT COUNT(*) FROM schema_migrations");
    $migCount = $stmtMig->fetchColumn();
    echo "[OK] Migration tracking functional. Total Executed Migrations: {$migCount}\n\n";

    if (empty($missingTables) && $missingTriggersCount === 0) {
        echo "Database schema integrity is healthy! Ready for Phase 4.\n";
    } else {
        echo "[WARNING] Database schema contains integrity errors. Check failed items above.\n";
        exit(1);
    }

} catch (Throwable $e) {
    echo "  [ERROR] Verification failed!\n";
    $safeMsg = $e->getMessage();
    $username = $_ENV['DB_USERNAME'] ?? '';
    $password = $_ENV['DB_PASSWORD'] ?? '';
    if ($password !== '') {
        $safeMsg = str_replace($password, '********', $safeMsg);
    }
    if ($username !== '') {
        $safeMsg = str_replace($username, '********', $safeMsg);
    }
    echo "  Message: " . $safeMsg . "\n";
    exit(1);
}
echo "===================================================\n";
