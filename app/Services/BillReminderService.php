<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\BillReminderRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\UserRepository;
use App\Repositories\ActivityLogRepository;
use App\Database\Database;
use Exception;

/**
 * Class BillReminderService
 * 
 * Manages invoice deadlines, recurring scheduling, and reminder alerts.
 */
class BillReminderService
{
    private BillReminderRepository $billRepo;
    private NotificationRepository $notificationRepo;
    private UserRepository $userRepo;
    private ActivityLogRepository $activityLogRepo;
    private ?MailService $mailService = null;

    public function __construct()
    {
        $this->billRepo = new BillReminderRepository();
        $this->notificationRepo = new NotificationRepository();
        $this->userRepo = new UserRepository();
        $this->activityLogRepo = new ActivityLogRepository();
    }

    /**
     * Set the mail service.
     */
    private function getMailService(): MailService
    {
        if ($this->mailService === null) {
            $this->mailService = new MailService();
        }
        return $this->mailService;
    }

    /**
     * Get bills list.
     */
    public function getBills(string $userId, ?string $status = null): array
    {
        $bills = $this->billRepo->list($userId, $status);
        $today = date('Y-m-d');
        
        // Dynamically compute 'overdue' status on read
        foreach ($bills as &$bill) {
            if ($bill['status'] === 'pending' && $bill['due_date'] < $today) {
                $bill['status'] = 'overdue';
            }
        }
        return $bills;
    }

    /**
     * Fetch upcoming bills.
     */
    public function getUpcomingBills(string $userId): array
    {
        return $this->billRepo->getUpcoming($userId);
    }

    /**
     * Fetch overdue bills.
     */
    public function getOverdueBills(string $userId): array
    {
        return $this->billRepo->getOverdue($userId);
    }

    /**
     * Fetch a single bill details.
     */
    public function getBill(string $id, string $userId): ?array
    {
        $bill = $this->billRepo->findById($id, $userId);
        if ($bill) {
            $today = date('Y-m-d');
            if ($bill['status'] === 'pending' && $bill['due_date'] < $today) {
                $bill['status'] = 'overdue';
            }
        }
        return $bill;
    }

    /**
     * Log a new bill reminder.
     */
    public function createBill(string $userId, array $data, ?string $ipAddress = null): array
    {
        $this->validate($data);

        $bill = $this->billRepo->create([
            'user_id' => $userId,
            'bill_name' => trim($data['bill_name']),
            'amount' => (float)$data['amount'],
            'due_date' => $data['due_date'],
            'remind_before_days' => (int)$data['remind_before_days'],
            'is_recurring' => isset($data['is_recurring']) && $data['is_recurring'] ? 1 : 0,
            'recurring_frequency' => isset($data['is_recurring']) && $data['is_recurring'] ? $data['recurring_frequency'] : null,
            'status' => 'pending',
            'category' => $data['category'] ?? 'others'
        ]);

        $this->activityLogRepo->record(
            $userId,
            'bill_created',
            'Bills',
            "Logged bill '{$data['bill_name']}' of ₹{$data['amount']} due on {$data['due_date']}.",
            ['bill_id' => $bill['id'], 'amount' => $data['amount']],
            $ipAddress
        );

        return $bill;
    }

    /**
     * Update an existing bill reminder.
     */
    public function updateBill(string $id, string $userId, array $data, ?string $ipAddress = null): bool
    {
        $existing = $this->billRepo->findById($id, $userId);
        if (!$existing) {
            throw new Exception("Bill reminder not found.", 404);
        }

        $this->validate($data);

        $res = $this->billRepo->update($id, $userId, [
            'bill_name' => trim($data['bill_name']),
            'amount' => (float)$data['amount'],
            'due_date' => $data['due_date'],
            'remind_before_days' => (int)$data['remind_before_days'],
            'is_recurring' => isset($data['is_recurring']) && $data['is_recurring'] ? 1 : 0,
            'recurring_frequency' => isset($data['is_recurring']) && $data['is_recurring'] ? $data['recurring_frequency'] : null,
            'status' => $data['status'] ?? 'pending',
            'category' => $data['category'] ?? 'others'
        ]);

        if ($res) {
            $this->activityLogRepo->record(
                $userId,
                'bill_updated',
                'Bills',
                "Updated bill '{$data['bill_name']}' of ₹{$data['amount']}.",
                ['bill_id' => $id, 'amount' => $data['amount']],
                $ipAddress
            );
        }

        return $res;
    }

    /**
     * Delete a bill reminder.
     */
    public function deleteBill(string $id, string $userId, ?string $ipAddress = null): bool
    {
        $existing = $this->billRepo->findById($id, $userId);
        if (!$existing) {
            throw new Exception("Bill reminder not found.", 404);
        }

        $res = $this->billRepo->delete($id, $userId);

        if ($res) {
            $this->activityLogRepo->record(
                $userId,
                'bill_deleted',
                'Bills',
                "Deleted bill reminder '{$existing['bill_name']}'.",
                ['bill_id' => $id],
                $ipAddress
            );
        }

        return $res;
    }

    /**
     * Mark a bill as paid, advancing its recurrence target inside a database transaction.
     */
    public function markBillAsPaid(string $id, string $userId, ?string $ipAddress = null): bool
    {
        $bill = $this->billRepo->findById($id, $userId);
        if (!$bill) {
            throw new Exception("Bill reminder not found.", 404);
        }

        if ($bill['status'] === 'paid') {
            throw new Exception("This bill has already been paid.", 409);
        }

        return Database::transaction(function() use ($id, $userId, $bill, $ipAddress) {
            // 1. Mark current occurrence as paid
            $this->billRepo->updateStatus($id, $userId, 'paid');

            // 2. If it is recurring, generate the next pending occurrence
            if ($bill['is_recurring'] && !empty($bill['recurring_frequency'])) {
                $nextDueDate = $this->calculateNextDueDate($bill['due_date'], $bill['recurring_frequency']);
                
                // Uniqueness/Race-safety check: prevent duplicate next occurrence
                $db = Database::connection();
                $stmtCheck = $db->prepare("
                    SELECT COUNT(*) FROM bill_reminders 
                    WHERE user_id = :user_id 
                      AND bill_name = :bill_name 
                      AND due_date = :due_date 
                      AND status = 'pending'
                ");
                $stmtCheck->execute([
                    ':user_id' => $userId,
                    ':bill_name' => $bill['bill_name'],
                    ':due_date' => $nextDueDate
                ]);
                $exists = (int)$stmtCheck->fetchColumn();

                if ($exists === 0) {
                    $this->billRepo->create([
                        'user_id' => $userId,
                        'bill_name' => $bill['bill_name'],
                        'amount' => $bill['amount'],
                        'due_date' => $nextDueDate,
                        'remind_before_days' => $bill['remind_before_days'],
                        'is_recurring' => true,
                        'recurring_frequency' => $bill['recurring_frequency'],
                        'status' => 'pending',
                        'category' => $bill['category']
                    ]);
                }
            }

            $this->activityLogRepo->record(
                $userId,
                'bill_marked_paid',
                'Bills',
                "Marked bill '{$bill['bill_name']}' of ₹{$bill['amount']} as paid.",
                ['bill_id' => $id, 'amount' => $bill['amount']],
                $ipAddress
            );

            return true;
        });
    }

    /**
     * Evaluate pending bills and generate in-app alerts and optional emails.
     */
    public function evaluateBillReminders(): int
    {
        $eligible = $this->billRepo->listEligibleReminders();
        $generatedCount = 0;
        $today = date('Y-m-d');

        foreach ($eligible as $bill) {
            $userId = $bill['user_id'];
            $billId = $bill['id'];
            $dueDate = $bill['due_date'];
            
            $isOverdue = ($dueDate < $today);
            $type = $isOverdue ? 'bill_overdue' : 'bill_reminder';
            
            // Deduplication key format: bill_reminder:{user_id}:{bill_id}:{due_date}:{notification_type}
            $eventKey = "bill_reminder:{$userId}:{$billId}:{$dueDate}:{$type}";
            
            $title = $isOverdue ? "Overdue Bill Alert!" : "Upcoming Bill Reminder";
            $message = $isOverdue 
                ? "Your bill '{$bill['bill_name']}' of ₹" . round((float)$bill['amount'], 2) . " was due on {$dueDate} and is now overdue."
                : "Your bill '{$bill['bill_name']}' of ₹" . round((float)$bill['amount'], 2) . " is due on {$dueDate}.";

            $notif = $this->notificationRepo->create([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'event_key' => $eventKey,
                'metadata' => ['bill_id' => $billId, 'due_date' => $dueDate, 'amount' => $bill['amount']]
            ]);

            if ($notif !== null) {
                $generatedCount++;

                // Optionally dispatch email notification safely
                try {
                    $user = $this->userRepo->findById($userId);
                    if ($user && !empty($user['email'])) {
                        $this->getMailService()->sendBillNotificationEmail(
                            $user['email'],
                            $user['full_name'],
                            $bill['bill_name'],
                            $dueDate,
                            (float)$bill['amount'],
                            $isOverdue
                        );
                    }
                } catch (\Throwable $err) {
                    // Suppress and log SMTP failures so we do not disrupt flow
                    \App\Helpers\Logger::error("SMTP Bill Alert delivery failed: " . $err->getMessage());
                }
            }
        }

        return $generatedCount;
    }

    /**
     * Calculate next due date respecting month boundaries.
     */
    public function calculateNextDueDate(string $currentDateStr, string $frequency): string
    {
        $currentDate = new \DateTime($currentDateStr);
        $startDay = (int)$currentDate->format('d');
        
        switch (strtolower($frequency)) {
            case 'weekly':
                $currentDate->modify('+7 days');
                return $currentDate->format('Y-m-d');
                
            case 'monthly':
                $monthsToAdd = 1;
                break;
            case 'quarterly':
                $monthsToAdd = 3;
                break;
            case 'yearly':
                $monthsToAdd = 12;
                break;
            default:
                return $currentDateStr;
        }
        
        $year = (int)$currentDate->format('Y');
        $month = (int)$currentDate->format('n');
        
        $month += $monthsToAdd;
        while ($month > 12) {
            $month -= 12;
            $year += 1;
        }
        
        $daysInTargetMonth = (int)cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $targetDay = min($startDay, $daysInTargetMonth);
        
        return sprintf('%04d-%02d-%02d', $year, $month, $targetDay);
    }

    /**
     * Validate bill input parameters.
     */
    private function validate(array $data): void
    {
        if (!isset($data['bill_name']) || empty(trim($data['bill_name']))) {
            throw new Exception("Bill name / description is required.", 422);
        }

        if (!isset($data['amount']) || (float)$data['amount'] < 0) {
            throw new Exception("Bill amount must be non-negative.", 422);
        }

        if (!isset($data['due_date']) || empty($data['due_date'])) {
            throw new Exception("Due date is required.", 422);
        }

        try {
            new \DateTime($data['due_date']);
        } catch (\Throwable $e) {
            throw new Exception("Invalid due date format.", 422);
        }

        if (isset($data['remind_before_days']) && (int)$data['remind_before_days'] < 0) {
            throw new Exception("Reminder lead days must be non-negative.", 422);
        }

        if (isset($data['is_recurring']) && $data['is_recurring']) {
            $freq = strtolower($data['recurring_frequency'] ?? '');
            if (!in_array($freq, ['weekly', 'monthly', 'quarterly', 'yearly'])) {
                throw new Exception("Invalid recurring frequency choice.", 422);
            }
        }

        if (isset($data['status'])) {
            $status = strtolower($data['status']);
            if (!in_array($status, ['pending', 'paid', 'overdue', 'cancelled'])) {
                throw new Exception("Invalid bill status choice.", 422);
            }
        }

        $allowedCategories = ['bills', 'rent', 'food', 'entertainment', 'travel', 'others'];
        if (isset($data['category']) && !in_array(strtolower($data['category']), $allowedCategories)) {
            throw new Exception("Invalid category selection.", 422);
        }
    }
}
