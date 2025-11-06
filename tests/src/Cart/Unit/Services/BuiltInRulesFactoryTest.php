<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Services\BuiltInRulesFactory;
use AIArmada\Cart\Storage\DatabaseStorage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

if (! function_exists('makeRulesFactoryCart')) {
    function makeRulesFactoryCart(string $suffix = ''): Cart
    {
        $identifier = 'builtin-rules-'.($suffix !== '' ? $suffix : uniqid());
        $storage = new DatabaseStorage(DB::connection('testing'), 'carts');

        return new Cart($storage, $identifier, events: null);
    }
}

describe('BuiltInRulesFactory', function (): void {
    it('lists all supported keys', function (): void {
        $factory = new BuiltInRulesFactory();

        expect($factory->getAvailableKeys())
            ->toBeArray()
            ->toContain('min-items', 'metadata-equals', 'time-window', 'customer-tag')
            ->and($factory->canCreateRules('min-items'))->toBeTrue()
            ->and($factory->canCreateRules('unknown-rule'))->toBeFalse();
    });

    it('evaluates minimum item threshold', function (): void {
        $factory = new BuiltInRulesFactory();
        $cart = makeRulesFactoryCart('min-items');

        $rules = $factory->createRules('min-items', ['context' => ['min' => 2]]);
        expect($rules)->toHaveCount(1);
        $rule = $rules[0];

        expect($rule($cart))->toBeFalse();

        $cart->add('sku-1', 'Item One', 10, 1);
        $cart->add('sku-2', 'Item Two', 15, 1);

        expect($rule($cart))->toBeTrue();
    });

    it('uses metadata equality without explicit context wrapper', function (): void {
        $factory = new BuiltInRulesFactory();
        $cart = makeRulesFactoryCart('metadata');
        $cart->setMetadata('tier', 'vip');

        $rules = $factory->createRules('metadata-equals', ['key' => 'tier', 'value' => 'vip']);
        $rule = $rules[0];

        expect($rule($cart))->toBeTrue();
    });

    it('matches item attribute values for cart and individual item scopes', function (): void {
        $factory = new BuiltInRulesFactory();
        $cart = makeRulesFactoryCart('attributes');

        $cart->add('book-1', 'Book One', 30, 1, ['category' => 'books']);
        $item = $cart->get('book-1');
        expect($item)->not->toBeNull();

        $rules = $factory->createRules('item-attribute-equals', [
            'context' => ['attribute' => 'category', 'value' => 'books'],
        ]);
        $rule = $rules[0];

        expect($rule($cart))->toBeTrue()
            ->and($rule($cart, $item))->toBeTrue();
    });

    it('honours configured time windows including overnight ranges', function (): void {
        $factory = new BuiltInRulesFactory();
        $cart = makeRulesFactoryCart('time-window');

        CarbonImmutable::setTestNow(CarbonImmutable::create(2025, 1, 1, 15, 0));
        $dayRule = $factory->createRules('time-window', ['context' => ['start' => '14:00', 'end' => '16:30']])[0];
        expect($dayRule($cart))->toBeTrue();

        CarbonImmutable::setTestNow(CarbonImmutable::create(2025, 1, 1, 1, 30));
        $overnightRule = $factory->createRules('time-window', ['context' => ['start' => '23:00', 'end' => '02:00']])[0];
        expect($overnightRule($cart))->toBeTrue();

        CarbonImmutable::setTestNow();
    });

    it('accepts day names for the day-of-week rule', function (): void {
        $factory = new BuiltInRulesFactory();
        $cart = makeRulesFactoryCart('day-of-week');

        CarbonImmutable::setTestNow(CarbonImmutable::create(2025, 1, 6, 9, 0)); // Monday
        $rule = $factory->createRules('day-of-week', ['context' => ['days' => ['monday', 'fri']]])[0];

        expect($rule($cart))->toBeTrue();
        CarbonImmutable::setTestNow();
    });

    it('detects customer tags stored in metadata', function (): void {
        $factory = new BuiltInRulesFactory();
        $cart = makeRulesFactoryCart('customer-tag');
        $cart->setMetadata('customer_tags', ['vip', 'wholesale']);

        $rule = $factory->createRules('customer-tag', ['context' => ['tag' => 'vip']])[0];

        expect($rule($cart))->toBeTrue();
    });

    it('recognises cart condition type presence', function (): void {
        $factory = new BuiltInRulesFactory();
        $cart = makeRulesFactoryCart('condition-type');

        $cart->addCondition(new CartCondition(
            name: 'order-tax',
            type: 'tax',
            target: 'total',
            value: '+5',
            attributes: [],
            order: 0,
            rules: null
        ));

        $rule = $factory->createRules('cart-condition-type-exists', ['context' => ['type' => 'tax']])[0];

        expect($rule($cart))->toBeTrue();
    });

    it('limits item quantity using item-level scope when provided', function (): void {
        $factory = new BuiltInRulesFactory();
        $cart = makeRulesFactoryCart('quantity');

        $cart->add('sku-qty', 'Limited Item', 12, 3);
        $item = $cart->get('sku-qty');
        expect($item)->not->toBeNull();

        $rule = $factory->createRules('item-quantity-at-most', ['context' => ['quantity' => 3]])[0];

        expect($rule($cart, $item))->toBeTrue();
    });
});
