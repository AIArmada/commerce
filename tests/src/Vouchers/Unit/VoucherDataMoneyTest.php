<?php

declare(strict_types=1);

use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherStatus;
use AIArmada\Vouchers\Data\VoucherType;

it('rejects floats for every integer monetary or limit field at the DTO boundary', function (): void {
    foreach ([
        'value',
        'min_cart_value',
        'max_discount',
        'credit_delay_hours',
        'usage_limit',
        'usage_limit_per_user',
        'affiliate_commission_value',
    ] as $field) {
        expect(fn () => VoucherData::fromArray([
            'id' => 'voucher-id',
            'code' => 'FLOAT-TEST',
            'name' => 'Float Test',
            'type' => VoucherType::Fixed->value,
            'value' => $field === 'value' ? 100.5 : 100,
            'currency' => 'MYR',
            'status' => 'active',
            $field => 100.5,
        ]))->toThrow(InvalidArgumentException::class);
    }
});

it('rejects noncanonical aliases at the DTO boundary', function (): void {
    expect(fn () => VoucherData::fromArray([
        'id' => 'voucher-id',
        'code' => 'FLOAT-ALIAS',
        'name' => 'Float Alias',
        'type' => VoucherType::Fixed->value,
        'value' => 100,
        'currency' => 'MYR',
        'status' => 'active',
        'minCartValue' => 100,
    ]))->toThrow(InvalidArgumentException::class);
});

it('round trips a voucher snapshot without leaking model state or mutable dates', function (): void {
    $date = new DateTimeImmutable('2026-12-01T10:00:00+08:00');
    $snapshot = VoucherData::fromArray([
        'code' => 'SNAPSHOT',
        'type' => 'fixed',
        'value' => 100,
        'status' => 'paused',
        'starts_at' => $date,
        'usage_limit' => 0,
    ]);

    $date->modify('+1 day');
    expect($snapshot->startsAt?->format('Y-m-d'))->toBe('2026-12-01')
        ->and($snapshot->status)->toBe(VoucherStatus::Paused)
        ->and(VoucherData::fromArray($snapshot->toArray())->toArray())->toBe($snapshot->toArray())
        ->and((new ReflectionClass(VoucherData::class))->isReadOnly())->toBeTrue();
});
