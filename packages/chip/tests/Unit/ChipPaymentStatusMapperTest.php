<?php

declare(strict_types=1);

namespace AIArmada\Chip\Tests\Unit;

use AIArmada\Chip\Support\ChipPaymentStatusMapper;
use AIArmada\Chip\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;

uses(TestCase::class);

it('maps created status', function (): void {
    expect(ChipPaymentStatusMapper::map('created'))->toBe(PaymentStatus::CREATED);
});

it('maps paid statuses', function (): void {
    expect(ChipPaymentStatusMapper::map('paid'))->toBe(PaymentStatus::PAID);
    expect(ChipPaymentStatusMapper::map('captured'))->toBe(PaymentStatus::PAID);
    expect(ChipPaymentStatusMapper::map('paid_authorized'))->toBe(PaymentStatus::PAID);
    expect(ChipPaymentStatusMapper::map('recurring_successful'))->toBe(PaymentStatus::PAID);
    expect(ChipPaymentStatusMapper::map('cleared'))->toBe(PaymentStatus::PAID);
    expect(ChipPaymentStatusMapper::map('settled'))->toBe(PaymentStatus::PAID);
});

it('maps refunded status', function (): void {
    expect(ChipPaymentStatusMapper::map('refunded'))->toBe(PaymentStatus::REFUNDED);
    expect(ChipPaymentStatusMapper::map('partially_refunded'))->toBe(PaymentStatus::PARTIALLY_REFUNDED);
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
    expect(ChipPaymentStatusMapper::map('attempted_capture'))->toBe(PaymentStatus::PROCESSING);
    expect(ChipPaymentStatusMapper::map('attempted_refund'))->toBe(PaymentStatus::PROCESSING);
    expect(ChipPaymentStatusMapper::map('pending_refund'))->toBe(PaymentStatus::PROCESSING);
});

it('maps authorized statuses', function (): void {
    expect(ChipPaymentStatusMapper::map('pending_capture'))->toBe(PaymentStatus::AUTHORIZED);
    expect(ChipPaymentStatusMapper::map('pending_release'))->toBe(PaymentStatus::AUTHORIZED);
    expect(ChipPaymentStatusMapper::map('hold'))->toBe(PaymentStatus::AUTHORIZED);
    expect(ChipPaymentStatusMapper::map('preauthorized'))->toBe(PaymentStatus::AUTHORIZED);
});

it('maps failure statuses', function (): void {
    expect(ChipPaymentStatusMapper::map('error'))->toBe(PaymentStatus::FAILED);
    expect(ChipPaymentStatusMapper::map('blocked'))->toBe(PaymentStatus::FAILED);
});

it('maps expired status', function (): void {
    expect(ChipPaymentStatusMapper::map('expired'))->toBe(PaymentStatus::EXPIRED);
    expect(ChipPaymentStatusMapper::map('overdue'))->toBe(PaymentStatus::EXPIRED);
});

it('maps disputed status', function (): void {
    expect(ChipPaymentStatusMapper::map('chargeback'))->toBe(PaymentStatus::DISPUTED);
});

it('defaults to pending for unknown statuses', function (): void {
    expect(ChipPaymentStatusMapper::map('unknown'))->toBe(PaymentStatus::PENDING);
    expect(ChipPaymentStatusMapper::map('some_random_status'))->toBe(PaymentStatus::PENDING);
});
