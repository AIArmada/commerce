<?php

declare(strict_types=1);

use AIArmada\Cart\Facades\Cart as CartFacade;
use AIArmada\FilamentCart\Models\Condition;

it('reproduces total-at-least hang', function (): void {
    Condition::factory()->create([
        'name' => 'free-shipping',
        'type' => 'shipping',
        'target' => 'cart@grand_total/aggregate',
        'target_definition' => conditionTargetDefinition('cart@grand_total/aggregate'),
        'value' => '-1000',
        'is_global' => true,
        'is_active' => true,
        'rules' => ['factory_keys' => ['total-at-least'], 'context' => ['amount' => 5000]],
    ]);

    CartFacade::add('sku-001', 'Product', 1000, 3);
    expect(CartFacade::getConditions()->has('free-shipping'))->toBeFalse();

    CartFacade::add('sku-002', 'Product', 1000, 2);
    expect(CartFacade::getConditions()->has('free-shipping'))->toBeTrue();
})->group('hang-reproduction');
