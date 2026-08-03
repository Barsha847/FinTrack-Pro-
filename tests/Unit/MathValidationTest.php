<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MathValidationTest extends TestCase
{
    public function test_roi_calculation_logic(): void
    {
        $buyPrice = 1000.0;
        $qty = 2.0;
        $currentValue = 2500.0;

        $investedAmount = $qty * $buyPrice;
        $profitLoss = $currentValue - $investedAmount;
        $roi = $investedAmount > 0 ? ($profitLoss / $investedAmount) * 100.0 : 0.0;

        $this->assertEquals(2000.0, $investedAmount);
        $this->assertEquals(500.0, $profitLoss);
        $this->assertEquals(25.0, $roi);
    }
    
    public function test_budget_percentage_calculation(): void
    {
        $spent = 450.0;
        $limit = 500.0;
        
        $pct = ($spent / $limit) * 100.0;
        
        $this->assertEquals(90.0, $pct);
        $this->assertTrue($pct >= 90.0);
    }
}
