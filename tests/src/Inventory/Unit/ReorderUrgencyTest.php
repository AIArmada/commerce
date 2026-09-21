<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\ReorderUrgency;

test('ReorderUrgency fromDaysUntilStockout calculates correctly', function (): void {
    expect(ReorderUrgency::fromDaysUntilStockout(null))->toBe(ReorderUrgency::Low);
    expect(ReorderUrgency::fromDaysUntilStockout(10, 5))->toBe(ReorderUrgency::Normal); // 10-5=5 <=7 => Normal
    expect(ReorderUrgency::fromDaysUntilStockout(5, 5))->toBe(ReorderUrgency::Critical); // 5-5=0 <=0 => Critical
    expect(ReorderUrgency::fromDaysUntilStockout(3, 5))->toBe(ReorderUrgency::Critical); // 3-5=-2 <=0 => Critical
    expect(ReorderUrgency::fromDaysUntilStockout(0, 5))->toBe(ReorderUrgency::Critical);
    expect(ReorderUrgency::fromDaysUntilStockout(-1, 5))->toBe(ReorderUrgency::Critical);
});
