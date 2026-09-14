<?php

declare(strict_types=1);

use AIArmada\Cart\Actions\ApplyStoredCondition;
use AIArmada\Cart\Services\BuiltInRulesFactory;
use AIArmada\Cart\Snapshots\CartInstanceManager;
use AIArmada\Cart\Snapshots\CartSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['cart.owner.enabled' => false]);

    $this->action = new ApplyStoredCondition(
        new CartInstanceManager(new BuiltInRulesFactory),
        new BuiltInRulesFactory,
    );
    $this->snapshot = new CartSnapshot(['identifier' => 'custom-cart', 'instance' => 'default']);
});

it('rejects custom conditions with missing keys', function (): void {
    expect(fn (): mixed => $this->action->applyCustom($this->snapshot, []))
        ->toThrow(Exception::class, 'name is required');
});

it('rejects custom conditions with a malformed target', function (): void {
    expect(fn (): mixed => $this->action->applyCustom($this->snapshot, [
        'name' => 'Bad Target',
        'type' => 'discount',
        'target' => 'not-a-target',
        'value' => '-10%',
    ]))->toThrow(Exception::class);
});

it('rejects custom conditions with non-scalar values', function (): void {
    expect(fn (): mixed => $this->action->applyCustom($this->snapshot, [
        'name' => 'Bad Value',
        'type' => 'discount',
        'target' => 'cart@cart_subtotal/aggregate',
        'value' => ['not' => 'scalar'],
    ]))->toThrow(Exception::class, 'must be a string, integer, or float');
});

it('rejects custom conditions with a non-integer order', function (): void {
    expect(fn (): mixed => $this->action->applyCustom($this->snapshot, [
        'name' => 'Bad Order',
        'type' => 'discount',
        'target' => 'cart@cart_subtotal/aggregate',
        'value' => '-10%',
        'order' => 'first',
    ]))->toThrow(Exception::class, 'order must be an integer');
});
