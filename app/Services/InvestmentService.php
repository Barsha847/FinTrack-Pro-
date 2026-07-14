<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\InvestmentRepository;
use App\Repositories\ActivityLogRepository;
use App\Database\Database;
use Exception;

/**
 * Class InvestmentService
 * 
 * Implements business rules, validation, and calculations for Investments.
 */
class InvestmentService
{
    private InvestmentRepository $investmentRepo;
    private ActivityLogRepository $activityLogRepo;

    // Allowed database enum values for asset_type
    private const ALLOWED_TYPES = [
        'stock', 'mutual_fund', 'fixed_deposit', 'gold', 'crypto', 
        'bond', 'real_estate', 'ppf', 'epf', 'sip', 'nps', 'other'
    ];

    // Map frontend categories to database types
    private const TYPE_MAP = [
        'stocks' => 'stock',
        'mutualfunds' => 'mutual_fund',
        'gold' => 'gold',
        'crypto' => 'crypto',
        'others' => 'other'
    ];

    public function __construct()
    {
        $this->investmentRepo = new InvestmentRepository();
        $this->activityLogRepo = new ActivityLogRepository();
    }

    /**
     * Map frontend asset category to DB representation.
     */
    private function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));
        return self::TYPE_MAP[$type] ?? (in_array($type, self::ALLOWED_TYPES, true) ? $type : 'other');
    }

    /**
     * Validate investment input data.
     */
    private function validate(array $data): array
    {
        $errors = [];

        if (empty($data['asset_name']) || trim((string)$data['asset_name']) === '') {
            $errors[] = "Asset name is required.";
        }

        $type = $this->normalizeType($data['asset_type'] ?? 'other');
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            $errors[] = "Invalid investment type.";
        }

        $buyPrice = (float)($data['buy_price'] ?? 0.0);
        if ($buyPrice <= 0) {
            $errors[] = "Purchase price (invested principal) must be greater than zero.";
        }

        $quantity = (float)($data['quantity'] ?? 1.0);
        if ($quantity <= 0) {
            $errors[] = "Quantity must be greater than zero.";
        }

        $currentValue = (float)($data['current_value'] ?? 0.0);
        if ($currentValue < 0) {
            $errors[] = "Current value cannot be negative.";
        }

        if (!empty($data['buy_date'])) {
            $d = \DateTime::createFromFormat('Y-m-d', $data['buy_date']);
            if (!$d || $d->format('Y-m-d') !== $data['buy_date']) {
                $errors[] = "Invalid purchase date format. Use Y-m-d.";
            }
        }

        return $errors;
    }

    /**
     * Create an investment log.
     */
    public function create(string $userId, array $data, ?string $ipAddress = null): array
    {
        // Default quantities and buy date if frontend doesn't provide them
        $data['user_id'] = $userId;
        $data['asset_type'] = $this->normalizeType($data['asset_type'] ?? 'other');
        $data['quantity'] = isset($data['quantity']) ? (float)$data['quantity'] : 1.0;
        $data['buy_price'] = isset($data['buy_price']) ? (float)$data['buy_price'] : (float)($data['invested_amount'] ?? 0.0);
        $data['current_value'] = isset($data['current_value']) ? (float)$data['current_value'] : $data['buy_price'];
        $data['buy_date'] = $data['buy_date'] ?? date('Y-m-d');
        $data['status'] = $data['status'] ?? 'active';

        $errors = $this->validate($data);
        if (!empty($errors)) {
            throw new Exception(implode(' ', $errors), 422);
        }

        return Database::transaction(function() use ($userId, $data, $ipAddress) {
            $id = $this->investmentRepo->create($data);
            
            // Record initial history
            $this->investmentRepo->createHistory($id, $userId, (float)$data['current_value']);

            // Record audit log
            $this->activityLogRepo->record(
                $userId,
                'investment_created',
                'Investments',
                "Created investment asset '{$data['asset_name']}' of type '{$data['asset_type']}'",
                ['investment_id' => $id, 'invested' => $data['buy_price'] * $data['quantity']],
                $ipAddress
            );

            return ['id' => $id];
        });
    }

    /**
     * Get details of an investment, enriched with ROI/returns.
     */
    public function get(string $id, string $userId): ?array
    {
        $investment = $this->investmentRepo->findById($id, $userId);
        if (!$investment) {
            return null;
        }

        return $this->enrichCalculations($investment);
    }

    /**
     * List all investments for a user, enriched with ROI and totals.
     */
    public function list(string $userId): array
    {
        $raw = $this->investmentRepo->list($userId);
        $list = [];
        $totalInvested = 0.0;
        $totalCurrent = 0.0;

        foreach ($raw as $item) {
            $enriched = $this->enrichCalculations($item);
            $list[] = $enriched;

            $totalInvested += (float)$enriched['invested_amount'];
            $totalCurrent += (float)$enriched['current_value'];
        }

        $totalProfitLoss = $totalCurrent - $totalInvested;
        $totalRoi = $totalInvested > 0.0 ? ($totalProfitLoss / $totalInvested) * 100.0 : 0.0;

        return [
            'investments' => $list,
            'summary' => [
                'total_invested' => round($totalInvested, 2),
                'total_current_value' => round($totalCurrent, 2),
                'total_profit_loss' => round($totalProfitLoss, 2),
                'portfolio_roi' => round($totalRoi, 2)
            ]
        ];
    }

    /**
     * Update an investment record.
     */
    public function update(string $id, string $userId, array $data, ?string $ipAddress = null): bool
    {
        $existing = $this->investmentRepo->findById($id, $userId);
        if (!$existing) {
            throw new Exception("Investment log not found.", 404);
        }

        // Merge existing values
        $merged = array_merge($existing, $data);
        $merged['asset_type'] = $this->normalizeType($merged['asset_type'] ?? 'other');
        $merged['quantity'] = isset($data['quantity']) ? (float)$data['quantity'] : (float)$existing['quantity'];
        $merged['buy_price'] = isset($data['buy_price']) ? (float)$data['buy_price'] : (isset($data['invested_amount']) ? (float)$data['invested_amount'] : (float)$existing['buy_price']);
        $merged['current_value'] = isset($data['current_value']) ? (float)$data['current_value'] : (float)$existing['current_value'];

        $errors = $this->validate($merged);
        if (!empty($errors)) {
            throw new Exception(implode(' ', $errors), 422);
        }

        return Database::transaction(function() use ($id, $userId, $merged, $existing, $ipAddress) {
            $updateData = [
                'asset_name' => $merged['asset_name'],
                'asset_type' => $merged['asset_type'],
                'quantity' => $merged['quantity'],
                'buy_price' => $merged['buy_price'],
                'current_value' => $merged['current_value'],
                'buy_date' => $merged['buy_date'],
                'notes' => $merged['notes'],
                'status' => $merged['status']
            ];

            $res = $this->investmentRepo->update($id, $userId, $updateData);

            // Record history if value changed
            if (abs((float)$merged['current_value'] - (float)$existing['current_value']) > 0.001) {
                $this->investmentRepo->createHistory($id, $userId, (float)$merged['current_value']);
            }

            // Log activity
            $this->activityLogRepo->record(
                $userId,
                'investment_updated',
                'Investments',
                "Updated investment '{$merged['asset_name']}' details",
                ['investment_id' => $id],
                $ipAddress
            );

            return $res;
        });
    }

    /**
     * Update current valuation of an investment.
     */
    public function updateCurrentValue(string $id, string $userId, float $currentValue, ?string $ipAddress = null): bool
    {
        if ($currentValue < 0) {
            throw new Exception("Current value cannot be negative.", 422);
        }

        $existing = $this->investmentRepo->findById($id, $userId);
        if (!$existing) {
            throw new Exception("Investment log not found.", 404);
        }

        return Database::transaction(function() use ($id, $userId, $currentValue, $existing, $ipAddress) {
            // Update the investments table current_value
            $res = $this->investmentRepo->update($id, $userId, ['current_value' => $currentValue]);
            
            // Insert log record in history
            $this->investmentRepo->createHistory($id, $userId, $currentValue);

            // Log activity
            $this->activityLogRepo->record(
                $userId,
                'investment_value_updated',
                'Investments',
                "Valuation updated for '{$existing['asset_name']}' from ₹{$existing['current_value']} to ₹{$currentValue}",
                ['investment_id' => $id, 'previous_value' => $existing['current_value'], 'new_value' => $currentValue],
                $ipAddress
            );

            return $res;
        });
    }

    /**
     * Delete an investment.
     */
    public function delete(string $id, string $userId, ?string $ipAddress = null): bool
    {
        $existing = $this->investmentRepo->findById($id, $userId);
        if (!$existing) {
            throw new Exception("Investment log not found.", 404);
        }

        return Database::transaction(function() use ($id, $userId, $existing, $ipAddress) {
            $res = $this->investmentRepo->delete($id, $userId);

            // Log activity
            $this->activityLogRepo->record(
                $userId,
                'investment_deleted',
                'Investments',
                "Deleted investment asset log '{$existing['asset_name']}'",
                ['investment_id' => $id],
                $ipAddress
            );

            return $res;
        });
    }

    /**
     * View history log of investment valuation changes.
     */
    public function getHistory(string $id, string $userId): array
    {
        $existing = $this->investmentRepo->findById($id, $userId);
        if (!$existing) {
            throw new Exception("Investment log not found.", 404);
        }

        return $this->investmentRepo->listHistory($id, $userId);
    }

    /**
     * Enrich raw DB record with profit, invested_amount, and ROI.
     */
    private function enrichCalculations(array $item): array
    {
        $qty = (float)$item['quantity'];
        $buyPrice = (float)$item['buy_price'];
        $currentValue = (float)$item['current_value'];

        $investedAmount = $qty * $buyPrice;
        $profitLoss = $currentValue - $investedAmount;
        $roi = $investedAmount > 0.0 ? ($profitLoss / $investedAmount) * 100.0 : 0.0;

        $item['invested_amount'] = round($investedAmount, 2);
        $item['profit_loss'] = round($profitLoss, 2);
        $item['roi'] = round($roi, 2);

        // Convert floats to nice displayable rounded strings for convenience
        $item['quantity'] = round($qty, 8);
        $item['buy_price'] = round($buyPrice, 4);
        $item['current_value'] = round($currentValue, 2);

        return $item;
    }
}
