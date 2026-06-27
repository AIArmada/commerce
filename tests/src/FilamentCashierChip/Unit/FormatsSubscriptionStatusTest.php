<?php

declare(strict_types=1);

use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\Commerce\Tests\FilamentCashierChip\TestCase;
use AIArmada\FilamentCashierChip\Support\FormatsSubscriptionStatus;

uses(TestCase::class);

function filamentCashierChip_invokeTrait(string $method, mixed ...$arguments): mixed
{
    $class = new class
    {
        use FormatsSubscriptionStatus;
    };

    $reflection = new ReflectionMethod($class::class, $method);

    return $reflection->invoke(null, ...$arguments);
}

it('formats subscription statuses, intervals, and amounts', function (): void {
    expect(filamentCashierChip_invokeTrait('getStatusColor', SubscriptionStatus::Active->value))->toBe('success');
    expect(filamentCashierChip_invokeTrait('getStatusColor', SubscriptionStatus::Trialing->value))->toBe('warning');
    expect(filamentCashierChip_invokeTrait('getStatusColor', SubscriptionStatus::Canceled->value))->toBe('danger');
    expect(filamentCashierChip_invokeTrait('getStatusColor', SubscriptionStatus::PastDue->value))->toBe('danger');
    expect(filamentCashierChip_invokeTrait('getStatusColor', SubscriptionStatus::Paused->value))->toBe('gray');
    expect(filamentCashierChip_invokeTrait('getStatusColor', SubscriptionStatus::Incomplete->value))->toBe('warning');
    expect(filamentCashierChip_invokeTrait('getStatusColor', SubscriptionStatus::Unpaid->value))->toBe('danger');
    expect(filamentCashierChip_invokeTrait('getStatusColor', 'other'))->toBe('gray');

    expect(filamentCashierChip_invokeTrait('formatStatus', SubscriptionStatus::Active->value))->toBeString();
    expect(filamentCashierChip_invokeTrait('formatStatus', SubscriptionStatus::Trialing->value))->toBeString();
    expect(filamentCashierChip_invokeTrait('formatStatus', SubscriptionStatus::Canceled->value))->toBeString();
    expect(filamentCashierChip_invokeTrait('formatStatus', SubscriptionStatus::PastDue->value))->toBeString();
    expect(filamentCashierChip_invokeTrait('formatStatus', SubscriptionStatus::Paused->value))->toBeString();
    expect(filamentCashierChip_invokeTrait('formatStatus', SubscriptionStatus::Incomplete->value))->toBeString();
    expect(filamentCashierChip_invokeTrait('formatStatus', SubscriptionStatus::IncompleteExpired->value))->toBeString();
    expect(filamentCashierChip_invokeTrait('formatStatus', SubscriptionStatus::Unpaid->value))->toBeString();
    expect(filamentCashierChip_invokeTrait('formatStatus', 'custom'))->toBe('Custom');

    expect(filamentCashierChip_invokeTrait('formatInterval', null, null))->toBe('—');
    expect(filamentCashierChip_invokeTrait('formatInterval', 'day', 1))->toBeString();
    expect(filamentCashierChip_invokeTrait('formatInterval', 'day', 2))->toBeString();
    expect(filamentCashierChip_invokeTrait('formatInterval', 'week', 1))->toBeString();
    expect(filamentCashierChip_invokeTrait('formatInterval', 'week', 2))->toBeString();
    expect(filamentCashierChip_invokeTrait('formatInterval', 'month', 1))->toBeString();
    expect(filamentCashierChip_invokeTrait('formatInterval', 'month', 2))->toBeString();
    expect(filamentCashierChip_invokeTrait('formatInterval', 'year', 1))->toBeString();
    expect(filamentCashierChip_invokeTrait('formatInterval', 'year', 2))->toBeString();
    expect(filamentCashierChip_invokeTrait('formatInterval', 'unknown', 3))->toBe('3 unknown');

    config()->set('cashier-chip.currency', 'usd');
    expect(filamentCashierChip_invokeTrait('formatAmount', 12345))->toBe('USD 123.45');
});
