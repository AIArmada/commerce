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

    it('always-true rule always returns true', function (): void {
        $factory = new BuiltInRulesFactory();
        $cart = makeRulesFactoryCart('always-true');

        $rules = $factory->createRules('always-true', []);
        expect($rules)->toHaveCount(1);
        $rule = $rules[0];

        expect($rule($cart))->toBeTrue();
    });

    it('always-false rule always returns false', function (): void {
        $factory = new BuiltInRulesFactory();
        $cart = makeRulesFactoryCart('always-false');

        $rules = $factory->createRules('always-false', []);
        expect($rules)->toHaveCount(1);
        $rule = $rules[0];

        expect($rule($cart))->toBeFalse();
    });

    it('has-any-item rule checks if cart has items', function (): void {
        $factory = new BuiltInRulesFactory();
        $cart = makeRulesFactoryCart('has-any-item');

        $rules = $factory->createRules('has-any-item', []);
        expect($rules)->toHaveCount(1);
        $rule = $rules[0];

        expect($rule($cart))->toBeFalse();

        $cart->add('item1', 'Item 1', 10, 1);
        expect($rule($cart))->toBeTrue();
    });

    it('subtotal-at-least rule checks subtotal threshold', function (): void {
        $factory = new BuiltInRulesFactory();
        $cart = makeRulesFactoryCart('subtotal-at-least');

        $rules = $factory->createRules('subtotal-at-least', ['context' => ['amount' => 50]]);
        expect($rules)->toHaveCount(1);
        $rule = $rules[0];

        $cart->add('item1', 'Item 1', 30, 1);
        expect($rule($cart))->toBeFalse();

        $cart->add('item2', 'Item 2', 25, 1);
        expect($rule($cart))->toBeTrue();
    });

    it('has-item rule checks for specific item', function (): void {
        $factory = new BuiltInRulesFactory();
        $cart = makeRulesFactoryCart('has-item');

        $rules = $factory->createRules('has-item', ['context' => ['id' => 'item1']]);
        expect($rules)->toHaveCount(1);
        $rule = $rules[0];

        expect($rule($cart))->toBeFalse();

        $cart->add('item1', 'Item 1', 10, 1);
        expect($rule($cart))->toBeTrue();
    });

    it('missing-item rule checks for absence of specific item', function (): void {
        $factory = new BuiltInRulesFactory();
        $cart = makeRulesFactoryCart('missing-item');

        $rules = $factory->createRules('missing-item', ['context' => ['id' => 'item1']]);
        expect($rules)->toHaveCount(1);
        $rule = $rules[0];

        expect($rule($cart))->toBeTrue();

        $cart->add('item1', 'Item 1', 10, 1);
        expect($rule($cart))->toBeFalse();
    });

    it('max-items rule checks maximum item count', function (): void {
        $factory = new BuiltInRulesFactory();
        $cart = makeRulesFactoryCart('max-items');

        $rules = $factory->createRules('max-items', ['context' => ['max' => 2]]);
        expect($rules)->toHaveCount(1);
        $rule = $rules[0];

        $cart->add('item1', 'Item 1', 10, 1);
        $cart->add('item2', 'Item 2', 10, 1);
        expect($rule($cart))->toBeTrue();

        $cart->add('item3', 'Item 3', 10, 1);
        expect($rule($cart))->toBeFalse();
    });

    it('min-quantity rule checks minimum total quantity', function (): void {
        $factory = new BuiltInRulesFactory();
        $cart = makeRulesFactoryCart('min-quantity');

        $rules = $factory->createRules('min-quantity', ['context' => ['min' => 5]]);
        expect($rules)->toHaveCount(1);
        $rule = $rules[0];

        $cart->add('item1', 'Item 1', 10, 3);
        expect($rule($cart))->toBeFalse();

        $cart->add('item2', 'Item 2', 10, 3);
        expect($rule($cart))->toBeTrue();
    });

    it('max-quantity rule checks maximum total quantity', function (): void {
        $factory = new BuiltInRulesFactory();
        $cart = makeRulesFactoryCart('max-quantity');

        $rules = $factory->createRules('max-quantity', ['context' => ['max' => 5]]);
        expect($rules)->toHaveCount(1);
        $rule = $rules[0];

        $cart->add('item1', 'Item 1', 10, 3);
        expect($rule($cart))->toBeTrue();

        $cart->add('item2', 'Item 2', 10, 3);
        expect($rule($cart))->toBeFalse();
    });

    it('subtotal-below rule checks subtotal threshold', function (): void {
        $factory = new BuiltInRulesFactory();
        $cart = makeRulesFactoryCart('subtotal-below');

        $rules = $factory->createRules('subtotal-below', ['context' => ['amount' => 50]]);
        expect($rules)->toHaveCount(1);
        $rule = $rules[0];

        $cart->add('item1', 'Item 1', 30, 1);
        expect($rule($cart))->toBeTrue();

        $cart->add('item2', 'Item 2', 25, 1);
        expect($rule($cart))->toBeFalse();
    });

    it('total-at-least rule checks total threshold', function (): void {
        $factory = new BuiltInRulesFactory();
        $cart = makeRulesFactoryCart('total-at-least');

        $rules = $factory->createRules('total-at-least', ['context' => ['amount' => 50]]);
        expect($rules)->toHaveCount(1);
        $rule = $rules[0];

        $cart->add('item1', 'Item 1', 30, 1);
        $cart->addTax('VAT', '10%');
        expect($rule($cart))->toBeFalse();

        $cart->add('item2', 'Item 2', 25, 1);
        expect($rule($cart))->toBeTrue();
    });

    it('has-metadata rule checks for metadata presence', function (): void {
        $factory = new BuiltInRulesFactory();
        $cart = makeRulesFactoryCart('has-metadata');

        $rules = $factory->createRules('has-metadata', ['context' => ['key' => 'promo']]);
        expect($rules)->toHaveCount(1);
        $rule = $rules[0];

        expect($rule($cart))->toBeFalse();

        $cart->setMetadata('promo', 'discount10');
        expect($rule($cart))->toBeTrue();
    });

    it('metadata-equals rule checks metadata value', function (): void {
        $factory = new BuiltInRulesFactory();
        $cart = makeRulesFactoryCart('metadata-equals');

        $rules = $factory->createRules('metadata-equals', ['context' => ['key' => 'tier', 'value' => 'vip']]);
        expect($rules)->toHaveCount(1);
        $rule = $rules[0];

        expect($rule($cart))->toBeFalse();

        $cart->setMetadata('tier', 'vip');
        expect($rule($cart))->toBeTrue();
    });

    it('item-list-includes-any and all rules behave correctly', function (): void {
        $factory = new BuiltInRulesFactory();
        $cart = makeRulesFactoryCart('item-list');

        $cart->add('item-1', 'Item 1', 10, 1);
        $cart->add('item-2', 'Item 2', 10, 1);

        $anyRule = $factory->createRules('item-list-includes-any', ['context' => ['ids' => ['item-2', 'missing']]])[0];
        $allRule = $factory->createRules('item-list-includes-all', ['context' => ['ids' => ['item-1', 'item-2']]])[0];

        expect($anyRule($cart))->toBeTrue();
        expect($allRule($cart))->toBeTrue();
    });

    it('item quantity and price rules consider item scope', function (): void {
        $factory = new BuiltInRulesFactory();
        $cart = makeRulesFactoryCart('item-scope');

        $cart->add('sku', 'Scoped Item', 20, 3);
        $item = $cart->get('sku');
        expect($item)->not->toBeNull();

        $qtyAtLeast = $factory->createRules('item-quantity-at-least', ['context' => ['quantity' => 2]])[0];
        $qtyAtMost = $factory->createRules('item-quantity-at-most', ['context' => ['quantity' => 3]])[0];
        $priceAtLeast = $factory->createRules('item-price-at-least', ['context' => ['amount' => 15]])[0];
        $priceAtMost = $factory->createRules('item-price-at-most', ['context' => ['amount' => 25]])[0];
        $totalAtLeast = $factory->createRules('item-total-at-least', ['context' => ['amount' => 60]])[0];
        $totalAtMost = $factory->createRules('item-total-at-most', ['context' => ['amount' => 70]])[0];

        expect($qtyAtLeast($cart, $item))->toBeTrue();
        expect($qtyAtMost($cart, $item))->toBeTrue();
        expect($priceAtLeast($cart, $item))->toBeTrue();
        expect($priceAtMost($cart, $item))->toBeTrue();
        expect($totalAtLeast($cart, $item))->toBeTrue();
        expect($totalAtMost($cart, $item))->toBeTrue();
    });

    it('item-has-condition and id-prefix rules behave correctly', function (): void {
        $factory = new BuiltInRulesFactory();
        $cart = makeRulesFactoryCart('item-condition');

        $cart->add('promo-1', 'Promo Item', 10, 1, [], [
            new CartCondition(
                name: 'promo',
                type: 'discount',
                target: 'item',
                value: '-10%'
            )
        ]);
        $item = $cart->get('promo-1');
        expect($item)->not->toBeNull();

        $hasCondition = $factory->createRules('item-has-condition', ['context' => ['condition' => 'promo']])[0];
        $idPrefix = $factory->createRules('item-id-prefix', ['context' => ['prefix' => 'promo-']])[0];

        expect($hasCondition($cart, $item))->toBeTrue();
        expect($idPrefix($cart, $item))->toBeTrue();
    });

    it('throws for unsupported key and invalid context', function (): void {
        $factory = new BuiltInRulesFactory();

        expect(fn () => $factory->createRules('unsupported-key', []))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => $factory->createRules('min-items', ['context' => 'not-an-array']))
            ->toThrow(InvalidArgumentException::class, 'metadata context must be an array');

        expect(fn () => $factory->createRules('min-items', ['context' => []]))
            ->toThrow(InvalidArgumentException::class, 'Missing context value [min]');
    });
});
