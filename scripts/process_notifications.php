<?php
declare(strict_types=1);

/**
 * process_notifications.php
 * 
 * Standalone CLI script suitable for execution via cron or Windows Task Scheduler.
 * Evaluates pending bill timelines and dispatches in-app alerts and emails.
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Services\BillReminderService;
use App\Helpers\Logger;

echo "===================================================\n";
echo "FinTrack Pro - Background Notification Processor\n";
echo "===================================================\n";
echo "Started processing at: " . date('Y-m-d H:i:s T') . "\n\n";

try {
    $billService = new BillReminderService();
    echo "Evaluating upcoming and overdue bill reminders...\n";
    $count = $billService->evaluateBillReminders();
    
    echo "\nProcessing complete! Generated {$count} new notification(s).\n";
    echo "===================================================\n";
    exit(0);

} catch (\Throwable $e) {
    echo "\n[CRITICAL ERROR] Notification processing aborted:\n";
    echo "  Message: " . $e->getMessage() . "\n";
    Logger::error("CLI Notification Processor crash: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
    echo "===================================================\n";
    exit(1);
}
