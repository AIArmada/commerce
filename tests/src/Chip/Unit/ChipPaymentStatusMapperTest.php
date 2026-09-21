<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Chip\Unit;

use AIArmada\Chip\Support\ChipPaymentStatusMapper;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;
use InvalidArgumentException;

it('rejects statuses outside the documented CHIP vocabulary', function (): void {
    expect(fn () => ChipPaymentStatusMapper::map('unknown'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => ChipPaymentStatusMapper::map('some_random_status'))
        ->toThrow(InvalidArgumentException::class);
});
it('gives a recognized webhook event precedence over its status', function (): void {
    expect(ChipPaymentStatusMapper::mapWebhook('error', 'purchase.paid'))->toBe(PaymentStatus::PAID);
    expect(ChipPaymentStatusMapper::mapWebhook('paid', 'purchase.payment_failure'))->toBe(PaymentStatus::FAILED);
});

it('falls back to the canonical status mapper for unknown event types', function (): void {
    expect(ChipPaymentStatusMapper::mapWebhook('pending_charge', 'custom.event'))->toBe(PaymentStatus::PENDING);
});
