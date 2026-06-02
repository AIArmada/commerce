<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Pages\CreateVoucher;
use AIArmada\FilamentVouchers\Support\ConditionTargetPreset;
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
