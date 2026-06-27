<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherAssignment;
use AIArmada\Vouchers\Models\VoucherTransaction;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Models\VoucherWallet;
use OwenIt\Auditing\Contracts\Auditable;

it('voucher model is auditable and activity loggable', function (): void {
    $traits = class_uses_recursive(Voucher::class);

    expect($traits)->toContain(HasCommerceAudit::class)
        ->and($traits)->toContain(LogsCommerceActivity::class)
        ->and(in_array(Auditable::class, class_implements(Voucher::class), true))->toBeTrue();
});

it('voucher wallet model is auditable and activity loggable', function (): void {
    $traits = class_uses_recursive(VoucherWallet::class);

    expect($traits)->toContain(HasCommerceAudit::class)
        ->and($traits)->toContain(LogsCommerceActivity::class)
        ->and(in_array(Auditable::class, class_implements(VoucherWallet::class), true))->toBeTrue();
});

it('voucher transaction model is auditable and activity loggable', function (): void {
    $traits = class_uses_recursive(VoucherTransaction::class);

    expect($traits)->toContain(HasCommerceAudit::class)
        ->and($traits)->toContain(LogsCommerceActivity::class)
        ->and(in_array(Auditable::class, class_implements(VoucherTransaction::class), true))->toBeTrue();
});

it('voucher usage model is auditable and activity loggable', function (): void {
    $traits = class_uses_recursive(VoucherUsage::class);

    expect($traits)->toContain(HasCommerceAudit::class)
        ->and($traits)->toContain(LogsCommerceActivity::class)
        ->and(in_array(Auditable::class, class_implements(VoucherUsage::class), true))->toBeTrue();
});

it('voucher assignment model is auditable and activity loggable', function (): void {
    $traits = class_uses_recursive(VoucherAssignment::class);

    expect($traits)->toContain(HasCommerceAudit::class)
        ->and($traits)->toContain(LogsCommerceActivity::class)
        ->and(in_array(Auditable::class, class_implements(VoucherAssignment::class), true))->toBeTrue();
});
