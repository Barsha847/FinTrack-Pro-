<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\LoanRepository;
use App\Repositories\ActivityLogRepository;
use App\Database\Database;
use Exception;
use DateTime;

/**
 * Class LoanService
 * 
 * Handles loan amortization schedules, EMI calculations, payment tracking, and status transitions.
 */
class LoanService
{
    private LoanRepository $loanRepo;
    private ActivityLogRepository $activityLogRepo;

    public function __construct()
    {
        $this->loanRepo = new LoanRepository();
        $this->activityLogRepo = new ActivityLogRepository();
    }

    /**
     * Validate loan inputs.
     */
    private function validate(array $data): array
    {
        $errors = [];

        if (empty($data['loan_name']) || trim((string)$data['loan_name']) === '') {
            $errors[] = "Lender/Description is required.";
        }

        $principal = (float)($data['principal_amount'] ?? 0.0);
        if ($principal <= 0) {
            $errors[] = "Principal amount must be greater than zero.";
        }

        $rate = (float)($data['interest_rate'] ?? 0.0);
        if ($rate < 0) {
            $errors[] = "Interest rate cannot be negative.";
        }

        $emi = (float)($data['emi_amount'] ?? 0.0);
        if ($emi <= 0) {
            $errors[] = "EMI amount must be greater than zero.";
        }

        $tenure = (int)($data['tenure_months'] ?? 0);
        if ($tenure <= 0) {
            $errors[] = "Tenure (Months) must be a positive integer.";
        }

        if (isset($data['paid_months'])) {
            $paid = (int)$data['paid_months'];
            if ($paid < 0) {
                $errors[] = "Paid installments count cannot be negative.";
            }
            if ($paid > $tenure) {
                $errors[] = "Paid installments cannot exceed total tenure months.";
            }
        }

        if (!empty($data['start_date'])) {
            $d = DateTime::createFromFormat('Y-m-d', $data['start_date']);
            if (!$d || $d->format('Y-m-d') !== $data['start_date']) {
                $errors[] = "Invalid start date format. Use Y-m-d.";
            }
        } else {
            $errors[] = "Start date is required.";
        }

        return $errors;
    }

    /**
     * Create a new loan and generate its complete EMI schedule.
     */
    public function createLoan(string $userId, array $data, ?string $ipAddress = null): array
    {
        $errors = $this->validate($data);
        if (!empty($errors)) {
            throw new Exception(implode(' ', $errors), 422);
        }

        $principal = (float)$data['principal_amount'];
        $emi = (float)$data['emi_amount'];
        $tenure = (int)$data['tenure_months'];
        $paidMonths = (int)($data['paid_months'] ?? 0);

        // Calculate initial remaining amount
        $totalPaid = $paidMonths * $emi;
        $remaining = max(0.0, $principal - $totalPaid);
        $status = ($remaining <= 0.0) ? 'paid' : 'active';

        // Generate EMI schedule dates
        $startDate = new DateTime($data['start_date']);
        $startDay = (int)$startDate->format('d');
        $emis = [];
        $nextDueDate = null;

        for ($i = 1; $i <= $tenure; $i++) {
            $dueDate = clone $startDate;
            $dueDate->modify("+{$i} months");
            
            // Adjust for day boundaries (e.g. 31st Jan -> last of Feb)
            if ((int)$dueDate->format('d') < $startDay && $dueDate->format('d') !== $dueDate->format('t')) {
                $dueDate->modify('last day of this month');
            }

            $dueDateStr = $dueDate->format('Y-m-d');
            $isPaid = ($i <= $paidMonths);
            $emiStatus = $isPaid ? 'paid' : ($dueDateStr < date('Y-m-d') ? 'overdue' : 'pending');

            $emis[] = [
                'user_id' => $userId,
                'amount' => $emi,
                'due_date' => $dueDateStr,
                'paid_date' => $isPaid ? date('Y-m-d') : null,
                'status' => $emiStatus
            ];

            // The earliest pending/overdue EMI becomes the next due date
            if (!$isPaid && $nextDueDate === null) {
                $nextDueDate = $dueDateStr;
            }
        }

        return Database::transaction(function() use ($userId, $data, $remaining, $status, $nextDueDate, $emis, $ipAddress) {
            $loanData = [
                'user_id' => $userId,
                'loan_name' => $data['loan_name'],
                'loan_type' => $data['loan_type'] ?? 'other',
                'lender_name' => $data['lender_name'] ?? $data['loan_name'],
                'principal_amount' => (float)$data['principal_amount'],
                'interest_rate' => (float)$data['interest_rate'],
                'remaining_amount' => $remaining,
                'emi_amount' => (float)$data['emi_amount'],
                'start_date' => $data['start_date'],
                'end_date' => null, // Filled on final payment
                'next_due_date' => $nextDueDate,
                'status' => $status,
                'tenure_months' => (int)$data['tenure_months']
            ];

            $loanId = $this->loanRepo->create($loanData);

            // Populate schedule table
            foreach ($emis as &$emi) {
                $emi['loan_id'] = $loanId;
            }
            $this->loanRepo->createEmiPayments($emis);

            // Log activity
            $this->activityLogRepo->record(
                $userId,
                'loan_created',
                'Loans',
                "Logged loan account '{$data['loan_name']}' from lender '{$loanData['lender_name']}'",
                ['loan_id' => $loanId, 'principal' => $loanData['principal_amount'], 'remaining' => $remaining],
                $ipAddress
            );

            return ['id' => $loanId];
        });
    }

    /**
     * Get a loan's details scoped by owner user ID.
     */
    public function getLoan(string $id, string $userId): ?array
    {
        $loan = $this->loanRepo->findById($id, $userId);
        if (!$loan) {
            return null;
        }

        // Dynamically compute progress percentages and enrich details
        return $this->enrichLoanDetails($loan, $userId);
    }

    /**
     * List all loans for a user.
     */
    public function listLoans(string $userId): array
    {
        $raw = $this->loanRepo->list($userId);
        $list = [];
        $totalOutstanding = 0.0;
        $activeLoansCount = 0;
        $totalEmiSum = 0.0;
        $totalPaidProgress = 0.0;

        foreach ($raw as $loan) {
            $enriched = $this->enrichLoanDetails($loan, $userId);
            $list[] = $enriched;

            if ($enriched['status'] !== 'closed' && $enriched['status'] !== 'paid') {
                $activeLoansCount++;
                $totalEmiSum += (float)$enriched['emi_amount'];
            }
            $totalOutstanding += (float)$enriched['remaining_amount'];
            $totalPaidProgress += (float)$enriched['progress_percent'];
        }

        $avgPaidProgress = count($list) > 0 ? ($totalPaidProgress / count($list)) : 0.0;

        return [
            'loans' => $list,
            'summary' => [
                'active_loans' => $activeLoansCount,
                'total_outstanding_balance' => round($totalOutstanding, 2),
                'monthly_emis_sum' => round($totalEmiSum, 2),
                'average_progress' => round($avgPaidProgress, 2)
            ]
        ];
    }

    /**
     * Delete a loan record.
     */
    public function deleteLoan(string $id, string $userId, ?string $ipAddress = null): bool
    {
        $loan = $this->loanRepo->findById($id, $userId);
        if (!$loan) {
            throw new Exception("Loan account not found.", 404);
        }

        return Database::transaction(function() use ($id, $userId, $loan, $ipAddress) {
            $res = $this->loanRepo->delete($id, $userId);

            // Log activity
            $this->activityLogRepo->record(
                $userId,
                'loan_deleted',
                'Loans',
                "Deleted loan account log '{$loan['loan_name']}'",
                ['loan_id' => $id],
                $ipAddress
            );

            return $res;
        });
    }

    /**
     * Update a loan and regenerate its complete schedule.
     */
    public function updateLoan(string $id, string $userId, array $data, ?string $ipAddress = null): bool
    {
        $errors = $this->validate($data);
        if (!empty($errors)) {
            throw new Exception(implode(' ', $errors), 422);
        }

        $existing = $this->loanRepo->findById($id, $userId);
        if (!$existing) {
            throw new Exception("Loan account not found.", 404);
        }

        $principal = (float)$data['principal_amount'];
        $emi = (float)$data['emi_amount'];
        $tenure = (int)$data['tenure_months'];
        $paidMonths = (int)($data['paid_months'] ?? 0);

        // Recalculate remaining amount and status
        $totalPaid = $paidMonths * $emi;
        $remaining = max(0.0, $principal - $totalPaid);
        $status = ($remaining <= 0.0) ? 'paid' : 'active';

        // Generate new EMI schedule
        $startDate = new DateTime($data['start_date']);
        $startDay = (int)$startDate->format('d');
        $emis = [];
        $nextDueDate = null;

        for ($i = 1; $i <= $tenure; $i++) {
            $dueDate = clone $startDate;
            $dueDate->modify("+{$i} months");
            if ((int)$dueDate->format('d') < $startDay && $dueDate->format('d') !== $dueDate->format('t')) {
                $dueDate->modify('last day of this month');
            }

            $dueDateStr = $dueDate->format('Y-m-d');
            $isPaid = ($i <= $paidMonths);
            $emiStatus = $isPaid ? 'paid' : ($dueDateStr < date('Y-m-d') ? 'overdue' : 'pending');

            $emis[] = [
                'user_id' => $userId,
                'loan_id' => $id,
                'amount' => $emi,
                'due_date' => $dueDateStr,
                'paid_date' => $isPaid ? date('Y-m-d') : null,
                'status' => $emiStatus
            ];

            if (!$isPaid && $nextDueDate === null) {
                $nextDueDate = $dueDateStr;
            }
        }

        return Database::transaction(function() use ($id, $userId, $data, $remaining, $status, $nextDueDate, $emis, $ipAddress) {
            // Delete old EMI records
            $db = Database::connection();
            $stmt = $db->prepare("DELETE FROM emi_payments WHERE loan_id = :loan_id AND user_id = :user_id");
            $stmt->execute([':loan_id' => $id, ':user_id' => $userId]);

            // Update Loan details
            $loanUpdate = [
                'loan_name' => $data['loan_name'],
                'loan_type' => $data['loan_type'] ?? 'other',
                'lender_name' => $data['lender_name'] ?? $data['loan_name'],
                'principal_amount' => $principal,
                'interest_rate' => (float)$data['interest_rate'],
                'remaining_amount' => $remaining,
                'emi_amount' => $emi,
                'start_date' => $data['start_date'],
                'next_due_date' => $nextDueDate,
                'status' => $status,
                'tenure_months' => $tenure
            ];
            $this->loanRepo->update($id, $userId, $loanUpdate);

            // Re-populate new EMI records
            $this->loanRepo->createEmiPayments($emis);

            // Log activity
            $this->activityLogRepo->record(
                $userId,
                'loan_updated',
                'Loans',
                "Updated loan account details for '{$data['loan_name']}' and regenerated schedule.",
                ['loan_id' => $id],
                $ipAddress
            );

            return true;
        });
    }

    /**
     * Get the EMI schedule for a loan.
     */
    public function getEmiSchedule(string $loanId, string $userId): array
    {
        $loan = $this->loanRepo->findById($loanId, $userId);
        if (!$loan) {
            throw new Exception("Loan account not found.", 404);
        }

        $raw = $this->loanRepo->listEmisForLoan($loanId, $userId);
        
        // Dynamically compute and flag 'overdue' states on read if payment is pending and due_date is in the past
        $today = date('Y-m-d');
        foreach ($raw as &$emi) {
            if ($emi['status'] === 'pending' && $emi['due_date'] < $today) {
                $emi['status'] = 'overdue';
            }
            $emi['amount'] = round((float)$emi['amount'], 2);
        }
        return $raw;
    }

    /**
     * Record a payment for a specific EMI.
     */
    public function recordEmiPayment(string $loanId, string $emiId, string $userId, ?string $ipAddress = null): bool
    {
        $loan = $this->loanRepo->findById($loanId, $userId);
        if (!$loan) {
            throw new Exception("Loan account not found.", 404);
        }

        $emi = $this->loanRepo->findEmiByIdAndLoan($emiId, $loanId, $userId);
        if (!$emi) {
            throw new Exception("EMI record not found or does not belong to the selected loan.", 404);
        }

        if ($emi['status'] === 'paid') {
            throw new Exception("This EMI installment has already been paid.", 409);
        }

        return Database::transaction(function() use ($loanId, $emiId, $userId, $loan, $emi, $ipAddress) {
            $paymentAmount = (float)$emi['amount'];
            $newRemaining = max(0.0, (float)$loan['remaining_amount'] - $paymentAmount);
            $newStatus = ($newRemaining <= 0.0) ? 'paid' : 'active';
            $endDate = ($newRemaining <= 0.0) ? date('Y-m-d') : null;

            // 1. Update EMI payment state
            $this->loanRepo->updateEmi($emiId, $loanId, $userId, [
                'status' => 'paid',
                'paid_date' => date('Y-m-d')
            ]);

            // 2. Fetch the remaining EMIs to recalculate next due date
            $emis = $this->loanRepo->listEmisForLoan($loanId, $userId);
            $nextDueDate = null;
            foreach ($emis as $e) {
                // Ignore the one we just paid
                if ($e['id'] === $emiId) {
                    continue;
                }
                if ($e['status'] !== 'paid') {
                    $nextDueDate = $e['due_date'];
                    break;
                }
            }

            // 3. Update the overall loan state
            $loanUpdate = [
                'remaining_amount' => $newRemaining,
                'status' => $newStatus,
                'next_due_date' => $nextDueDate
            ];
            if ($endDate !== null) {
                $loanUpdate['end_date'] = $endDate;
            }

            $res = $this->loanRepo->update($loanId, $userId, $loanUpdate);

            // Log activity
            $this->activityLogRepo->record(
                $userId,
                'emi_payment_recorded',
                'Loans',
                "Recorded EMI payment of ₹{$paymentAmount} for loan '{$loan['loan_name']}'. Outstanding balance: ₹{$newRemaining}",
                ['loan_id' => $loanId, 'emi_id' => $emiId, 'paid_amount' => $paymentAmount, 'remaining' => $newRemaining],
                $ipAddress
            );

            if ($newStatus === 'paid') {
                $this->activityLogRepo->record(
                    $userId,
                    'loan_paid',
                    'Loans',
                    "Loan '{$loan['loan_name']}' is now fully repaid and marked as closed.",
                    ['loan_id' => $loanId],
                    $ipAddress
                );
            }

            return $res;
        });
    }

    /**
     * Enrich a loan record with progress ratio and numeric precision.
     */
    private function enrichLoanDetails(array $loan, string $userId): array
    {
        $principal = (float)$loan['principal_amount'];
        $remaining = (float)$loan['remaining_amount'];
        $emiAmount = (float)$loan['emi_amount'];
        $tenure = (int)$loan['tenure_months'];

        // Get paid months from the database schedule
        $emis = $this->loanRepo->listEmisForLoan($loan['id'], $userId);
        $paidCount = 0;
        $overdueCount = 0;
        $today = date('Y-m-d');

        foreach ($emis as $emi) {
            if ($emi['status'] === 'paid') {
                $paidCount++;
            } elseif ($emi['due_date'] < $today) {
                $overdueCount++;
            }
        }

        // Progress ratios
        $progressPct = $tenure > 0 ? ($paidCount / $tenure) * 100.0 : 0.0;

        // Dynamically flag loan as overdue if it has unpaid, past due EMIs
        if ($loan['status'] === 'active' && $overdueCount > 0) {
            $loan['status'] = 'overdue';
        }

        $loan['principal_amount'] = round($principal, 2);
        $loan['remaining_amount'] = round($remaining, 2);
        $loan['emi_amount'] = round($emiAmount, 2);
        $loan['interest_rate'] = round((float)$loan['interest_rate'], 4);
        $loan['paid_months'] = $paidCount;
        $loan['progress_percent'] = round($progressPct, 2);
        $loan['overdue_emi_count'] = $overdueCount;

        return $loan;
    }
}
