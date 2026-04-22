<?php

declare(strict_types=1);

use AIArmada\Checkout\Integrations\VouchersAdapter;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Vouchers\Contracts\VoucherServiceInterface;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\States\VoucherStatus;

use function Pest\Laravel\mock;

it('normalizes voucher validation results', function (): void {
    $session = new CheckoutSession;
    $session->id = 'session-1';
    $session->subtotal = 1200;
    $session->currency = 'MYR';

    $adapter = new VouchersAdapter;

    if (! interface_exists(VoucherServiceInterface::class)) {
        $result = $adapter->validateVoucher('CODE', $session);

        expect($result['valid'])->toBeFalse()
            ->and($result['message'])->toBe('Vouchers not available')
            ->and($result['voucher'])->toBeNull();

        return;
    }

    $status = VoucherStatus::fromString('active', new Voucher);

    $voucherData = new VoucherData(
        id: 'voucher-1',
        code: 'CODE',
        name: 'Test Voucher',
        description: null,
        type: VoucherType::Fixed,
        value: 500,
        valueConfig: null,
        creditDestination: null,
        creditDelayHours: 0,
        currency: 'MYR',
        minCartValue: null,
        maxDiscount: null,
        usageLimit: null,
        usageLimitPerUser: null,
        allowsManualRedemption: false,
        ownerId: null,
        ownerType: null,
        startsAt: null,
        expiresAt: null,
        status: $status,
        targetDefinition: null,
        metadata: null,
    );

    $service = mock(VoucherServiceInterface::class);
    $service->shouldReceive('validate')
        ->once()
        ->andReturn(VoucherValidationResult::valid());
    $service->shouldReceive('find')
        ->once()
        ->andReturn($voucherData);

    app()->instance(VoucherServiceInterface::class, $service);

    $result = $adapter->validateVoucher('CODE', $session);

    expect($result['valid'])->toBeTrue()
        ->and($result['message'])->toBeNull()
        ->and($result['voucher'])->toBeArray()
        ->and($result['voucher']['code'])->toBe('CODE')
        ->and($result['voucher']['type'])->toBe('fixed');
});
