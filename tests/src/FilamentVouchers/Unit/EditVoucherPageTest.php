<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Pages\EditVoucher;
use AIArmada\FilamentVouchers\Support\ConditionTargetPreset;
use Illuminate\Validation\ValidationException;

uses(TestCase::class);

it('hydrates and persists condition target state on voucher edit', function (): void {
    $page = app(EditVoucher::class);

    $hydrate = new ReflectionMethod(EditVoucher::class, 'hydrateConditionTargetState');

    $data = $hydrate->invoke($page, []);

    expect($data)->toHaveKeys([
        'condition_target_dsl',
        'condition_target_preset',
        'target_definition',
    ]);

    $persist = new ReflectionMethod(EditVoucher::class, 'persistConditionTargetDefinition');

    expect(fn () => $persist->invoke($page, [
        'condition_target_preset' => ConditionTargetPreset::Custom->value,
        'condition_target_dsl' => '',
    ]))
        ->toThrow(ValidationException::class);

    $ok = $persist->invoke($page, [
        'condition_target_preset' => $data['condition_target_preset'],
        'metadata' => ['foo' => 'bar'],
    ]);

    expect($ok)->toHaveKey('target_definition');
    expect($ok['metadata'])->toBe(['foo' => 'bar'])
        ->and($ok['target_definition']['scope'])->toBe('cart')
        ->and($ok['target_definition']['phase'])->toBe('cart_subtotal')
        ->and($ok['target_definition']['application'])->toBe('aggregate');
});
