<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Pages\CreateVoucher;
use AIArmada\FilamentVouchers\Support\ConditionTargetPreset;
use AIArmada\Vouchers\Enums\VoucherType;
use Illuminate\Validation\ValidationException;

uses(TestCase::class);

it('persists preset condition targets without requiring target dsl input', function (): void {
    $page = app(CreateVoucher::class);

    $persist = new ReflectionMethod(CreateVoucher::class, 'persistConditionTargetDefinition');

    $data = $persist->invoke($page, [
        'condition_target_preset' => ConditionTargetPreset::GrandTotal->value,
        'metadata' => ['target_definition' => ['stale' => true], 'foo' => 'bar'],
    ]);

    expect($data)->toHaveKey('target_definition')
        ->and($data['target_definition']['scope'])->toBe('cart')
        ->and($data['target_definition']['phase'])->toBe('grand_total')
        ->and($data['target_definition']['application'])->toBe('aggregate')
        ->and($data['metadata'])->toBe(['foo' => 'bar'])
        ->and($data)->not->toHaveKey('condition_target_preset')
        ->and($data)->not->toHaveKey('condition_target_dsl');
});

it('requires target dsl only for custom condition targets', function (): void {
    $page = app(CreateVoucher::class);

    $persist = new ReflectionMethod(CreateVoucher::class, 'persistConditionTargetDefinition');

    expect(fn () => $persist->invoke($page, [
        'condition_target_preset' => ConditionTargetPreset::Custom->value,
        'condition_target_dsl' => '',
    ]))->toThrow(ValidationException::class);

    expect(fn () => $persist->invoke($page, [
        'condition_target_preset' => ConditionTargetPreset::Custom->value,
        'condition_target_dsl' => [],
    ]))->toThrow(ValidationException::class);

    $data = $persist->invoke($page, [
        'condition_target_preset' => ConditionTargetPreset::Custom->value,
        'condition_target_dsl' => 'items@item_discount/per-item',
    ]);

    expect($data['target_definition']['scope'])->toBe('items')
        ->and($data['target_definition']['phase'])->toBe('item_discount')
        ->and($data['target_definition']['application'])->toBe('per-item');
});

it('rejects invalid condition target presets', function (): void {
    $page = app(CreateVoucher::class);

    $persist = new ReflectionMethod(CreateVoucher::class, 'persistConditionTargetDefinition');

    expect(fn () => $persist->invoke($page, [
        'condition_target_preset' => 'not-a-preset',
    ]))->toThrow(ValidationException::class);
});

it('rejects affiliate program ids outside the current owner scope on create', function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'voucher-page-owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'voucher-page-owner-b@example.com',
        'password' => 'secret',
    ]);

    $foreignProgram = OwnerContext::withOwner($ownerB, function (): AffiliateProgram {
        return AffiliateProgram::create([
            'name' => 'Foreign Voucher Program',
            'status' => ProgramStatus::Active,
            'requires_approval' => false,
            'visibility' => ProgramVisibility::Public,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);
    });

    $page = app(CreateVoucher::class);
    $mutate = new ReflectionMethod(CreateVoucher::class, 'mutateFormDataBeforeCreate');

    expect(fn (): array => OwnerContext::withOwner($ownerA, function () use ($page, $mutate, $foreignProgram): array {
        return $mutate->invoke($page, [
            'code' => 'PAGE-FOREIGN-PROGRAM',
            'type' => VoucherType::Fixed->value,
            'value' => 1000,
            'condition_target_preset' => ConditionTargetPreset::GrandTotal->value,
            'affiliate_program_id' => $foreignProgram->id,
        ]);
    }))->toThrow(ValidationException::class);
});
