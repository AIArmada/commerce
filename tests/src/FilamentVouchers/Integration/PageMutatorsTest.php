<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentVouchers\Resources\FraudSignalResource\Pages\ListFraudSignals;
use AIArmada\FilamentVouchers\Resources\GiftCardResource\Pages\CreateGiftCard;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Pages\CreateVoucher;
use AIArmada\FilamentVouchers\Resources\VoucherUsageResource\Pages\ListVoucherUsages;
use Illuminate\Validation\ValidationException;

uses(TestCase::class);

it('mutates gift card form data defaults', function (): void {
    $page = app(CreateGiftCard::class);

    $method = new ReflectionMethod(CreateGiftCard::class, 'mutateFormDataBeforeCreate');
    $method->setAccessible(true);

    $data = $method->invoke($page, ['initial_balance' => 1234]);

    expect($data['current_balance'])->toBe(1234);
});

it('persists condition target definitions when creating vouchers', function (): void {
    $page = app(CreateVoucher::class);

    $method = new ReflectionMethod(CreateVoucher::class, 'persistConditionTargetDefinition');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($page, ['condition_target_dsl' => '']))
        ->toThrow(ValidationException::class);

    expect(fn () => $method->invoke($page, ['condition_target_dsl' => 'not-a-dsl']))
        ->toThrow(ValidationException::class);

    $ok = $method->invoke($page, [
        'condition_target_dsl' => 'cart@cart_subtotal/aggregate',
        'metadata' => ['foo' => 'bar'],
        'condition_target_preset' => 'cart_subtotal',
    ]);

    expect($ok)->toHaveKey('target_definition');
    expect($ok)->toHaveKey('metadata');
    expect($ok['metadata'])->toBe(['foo' => 'bar']);
    expect($ok)->not->toHaveKey('condition_target_dsl');
    expect($ok)->not->toHaveKey('condition_target_preset');
});

it('covers list page widgets and titles', function (): void {
    $listFraud = app(ListFraudSignals::class);

    $method = new ReflectionMethod(ListFraudSignals::class, 'getHeaderWidgets');
    $method->setAccessible(true);

    expect($method->invoke($listFraud))->toBeArray();

    $listUsages = app(ListVoucherUsages::class);
    expect($listUsages->getTitle())->toBe('Voucher Usage');
});
