<?php

declare(strict_types=1);

use AIArmada\CashierChip\Subscription;
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
    $reflection->setAccessible(true);

    return $reflection->invoke(null, ...$arguments);
}

it('formats subscription statuses, intervals, and amounts', function (): void {
    expect(filamentCashierChip_invokeTrait('getStatusColor', Subscription::STATUS_ACTIVE))->toBe('success');
    expect(filamentCashierChip_invokeTrait('getStatusColor', Subscription::STATUS_TRIALING))->toBe('warning');
    expect(filamentCashierChip_invokeTrait('getStatusColor', Subscription::STATUS_CANCELED))->toBe('danger');
    expect(filamentCashierChip_invokeTrait('getStatusColor', Subscription::STATUS_PAST_DUE))->toBe('danger');
    expect(filamentCashierChip_invokeTrait('getStatusColor', Subscription::STATUS_PAUSED))->toBe('gray');
    expect(filamentCashierChip_invokeTrait('getStatusColor', Subscription::STATUS_INCOMPLETE))->toBe('warning');
    expect(filamentCashierChip_invokeTrait('getStatusColor', Subscription::STATUS_UNPAID))->toBe('danger');
    expect(filamentCashierChip_invokeTrait('getStatusColor', 'other'))->toBe('gray');

    expect(filamentCashierChip_invokeTrait('formatStatus', Subscription::STATUS_ACTIVE))->toBeString();
    expect(filamentCashierChip_invokeTrait('formatStatus', Subscription::STATUS_TRIALING))->toBeString();
    expect(filamentCashierChip_invokeTrait('formatStatus', Subscription::STATUS_CANCELED))->toBeString();
    expect(filamentCashierChip_invokeTrait('formatStatus', Subscription::STATUS_PAST_DUE))->toBeString();
    expect(filamentCashierChip_invokeTrait('formatStatus', Subscription::STATUS_PAUSED))->toBeString();
    expect(filamentCashierChip_invokeTrait('formatStatus', Subscription::STATUS_INCOMPLETE))->toBeString();
    expect(filamentCashierChip_invokeTrait('formatStatus', Subscription::STATUS_INCOMPLETE_EXPIRED))->toBeString();
    expect(filamentCashierChip_invokeTrait('formatStatus', Subscription::STATUS_UNPAID))->toBeString();
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
