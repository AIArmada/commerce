<?php

declare(strict_types=1);

use AIArmada\Docs\Enums\ResetFrequency;
use Carbon\CarbonImmutable;

test('reset frequency current period key for daily returns date', function (): void {
    CarbonImmutable::setTestNow('2024-06-15 10:30:00');

    expect(ResetFrequency::Daily->getCurrentPeriodKey())->toBe('2024-06-15');

    CarbonImmutable::setTestNow();
});

test('reset frequency current period key for monthly returns year-month', function (): void {
    CarbonImmutable::setTestNow('2024-06-15 10:30:00');

    expect(ResetFrequency::Monthly->getCurrentPeriodKey())->toBe('2024-06');

    CarbonImmutable::setTestNow();
});

test('reset frequency current period key for yearly returns year', function (): void {
    CarbonImmutable::setTestNow('2024-06-15 10:30:00');

    expect(ResetFrequency::Yearly->getCurrentPeriodKey())->toBe('2024');

    CarbonImmutable::setTestNow();
});
