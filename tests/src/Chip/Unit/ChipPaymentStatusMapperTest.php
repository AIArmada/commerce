<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Chip\Unit;

use AIArmada\Chip\Support\ChipPaymentStatusMapper;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;
use InvalidArgumentException;

it('maps created status', function (): void {
    expect(ChipPaymentStatusMapper::map('created'))->toBe(PaymentStatus::CREATED);
});

it('maps paid statuses', function (): void {
    expect(ChipPaymentStatusMapper::map('paid'))->toBe(PaymentStatus::PAID);
    expect(ChipPaymentStatusMapper::map('cleared'))->toBe(PaymentStatus::PAID);
    expect(ChipPaymentStatusMapper::map('settled'))->toBe(PaymentStatus::PAID);
});

it('maps refunded status', function (): void {
    expect(ChipPaymentStatusMapper::map('refunded'))->toBe(PaymentStatus::REFUNDED);
});

it('maps cancelled status', function (): void {
    expect(ChipPaymentStatusMapper::map('cancelled'))->toBe(PaymentStatus::CANCELLED);
    expect(ChipPaymentStatusMapper::map('released'))->toBe(PaymentStatus::CANCELLED);
});

it('maps pending statuses', function (): void {
    expect(ChipPaymentStatusMapper::map('sent'))->toBe(PaymentStatus::PENDING);
    expect(ChipPaymentStatusMapper::map('viewed'))->toBe(PaymentStatus::PENDING);
    expect(ChipPaymentStatusMapper::map('pending_execute'))->toBe(PaymentStatus::PENDING);
    expect(ChipPaymentStatusMapper::map('pending_charge'))->toBe(PaymentStatus::PENDING);
});

it('maps processing statuses', function (): void {
    expect(ChipPaymentStatusMapper::map('pending_capture'))->toBe(PaymentStatus::PROCESSING);
    expect(ChipPaymentStatusMapper::map('pending_release'))->toBe(PaymentStatus::PROCESSING);
    expect(ChipPaymentStatusMapper::map('pending_refund'))->toBe(PaymentStatus::PROCESSING);
});

it('maps authorized statuses', function (): void {
    expect(ChipPaymentStatusMapper::map('hold'))->toBe(PaymentStatus::AUTHORIZED);
    expect(ChipPaymentStatusMapper::map('preauthorized'))->toBe(PaymentStatus::AUTHORIZED);
});

it('maps failure statuses', function (): void {
    expect(ChipPaymentStatusMapper::map('error'))->toBe(PaymentStatus::FAILED);
    expect(ChipPaymentStatusMapper::map('blocked'))->toBe(PaymentStatus::FAILED);
});

it('maps expired status', function (): void {
    expect(ChipPaymentStatusMapper::map('expired'))->toBe(PaymentStatus::EXPIRED);
    expect(ChipPaymentStatusMapper::map('overdue'))->toBe(PaymentStatus::PENDING);
});

it('maps disputed status', function (): void {
    expect(ChipPaymentStatusMapper::map('chargeback'))->toBe(PaymentStatus::DISPUTED);
});

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
