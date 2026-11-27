<?php

declare(strict_types=1);

use AIArmada\Cart\Features\Conditions\Actions\ApplyStoredCondition;
use AIArmada\Cart\Features\Snapshots\Api\SnapshotConditionLookup;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshot;
use AIArmada\Cart\Services\BuiltInRulesFactory;
use AIArmada\Cart\Snapshots\CartInstanceManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['cart.owner.enabled' => false]);

    $this->action = new ApplyStoredCondition(
        new CartInstanceManager(new BuiltInRulesFactory),
        new BuiltInRulesFactory,
        app(SnapshotConditionLookup::class),
    );
    $this->snapshot = CartSnapshot::query()->create(['identifier' => 'custom-cart', 'instance' => 'default']);
});

it('rejects custom conditions with missing keys', function (): void {
    expect(fn (): mixed => $this->action->applyCustom((string) $this->snapshot->getKey(), []))
        ->toThrow(Exception::class, 'name is required');
});

it('rejects custom conditions with a malformed target', function (): void {
    expect(fn (): mixed => $this->action->applyCustom((string) $this->snapshot->getKey(), [
        'name' => 'Bad Target',
        'type' => 'discount',
        'target' => 'not-a-target',
        'value' => '-10%',
    ]))->toThrow(Exception::class);
});

it('rejects custom conditions with non-scalar values', function (): void {
    expect(fn (): mixed => $this->action->applyCustom((string) $this->snapshot->getKey(), [
        'name' => 'Bad Value',
        'type' => 'discount',
        'target' => 'cart@cart_subtotal/aggregate',
        'value' => ['not' => 'scalar'],
    ]))->toThrow(Exception::class, 'must be a string, integer, or float');
});

it('rejects custom conditions with a non-integer order', function (): void {
    expect(fn (): mixed => $this->action->applyCustom((string) $this->snapshot->getKey(), [
        'name' => 'Bad Order',
        'type' => 'discount',
        'target' => 'cart@cart_subtotal/aggregate',
        'value' => '-10%',
        'order' => 'first',
    ]))->toThrow(Exception::class, 'order must be an integer');
});
