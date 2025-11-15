<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Models\CartItem;

beforeEach(function (): void {
    $this->condition1 = new CartCondition(
        name: 'discount-10',
        type: 'discount',
        target: 'subtotal',
        value: '-10%'
    );
    $this->condition2 = new CartCondition(
        name: 'shipping',
        type: 'charge',
        target: 'subtotal',
        value: '+15'
    );
});

it('can create cart item with basic data', function (): void {
    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 99.99,
        quantity: 2
    );

    expect($item->id)->toBe('product-1')
        ->and($item->name)->toBe('Test Product')
        ->and($item->price)->toBe(99.99)
        ->and($item->quantity)->toBe(2)
        ->and($item->attributes)->toBeInstanceOf(Illuminate\Support\Collection::class)
        ->and($item->attributes->toArray())->toBe([])
        ->and($item->conditions)->toBeInstanceOf(AIArmada\Cart\Collections\CartConditionCollection::class)
        ->and($item->conditions->isEmpty())->toBeTrue()
        ->and($item->associatedModel)->toBeNull();
});

it('can create cart item with all data', function (): void {
    $model = new stdClass;
    $model->id = 123;

    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 99.99,
        quantity: 2,
        attributes: ['color' => 'red', 'size' => 'large'],
        conditions: [$this->condition1, $this->condition2],
        associatedModel: $model
    );

    expect($item->id)->toBe('product-1')
        ->and($item->name)->toBe('Test Product')
        ->and($item->price)->toBe(99.99)
        ->and($item->quantity)->toBe(2)
        ->and($item->attributes->toArray())->toBe(['color' => 'red', 'size' => 'large'])
        ->and($item->conditions->count())->toBe(2)
        ->and($item->associatedModel)->toBe($model);
});

it('can calculate price sum', function (): void {
    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 50.0,
        quantity: 3
    );

    expect($item->getSubtotal())
        ->toBeInstanceOf(Akaunting\Money\Money::class)
        ->and($item->getRawSubtotal())->toBe(150.0);
});

it('can get single attribute', function (): void {
    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 99.99,
        quantity: 1,
        attributes: ['color' => 'red', 'size' => 'large']
    );

    expect($item->getAttribute('color'))->toBe('red')
        ->and($item->getAttribute('size'))->toBe('large')
        ->and($item->getAttribute('nonexistent'))->toBeNull();
});

it('can check if associated with model', function (): void {
    $model = new stdClass;
    $model->id = 123;

    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 99.99,
        quantity: 1,
        associatedModel: $model
    );

    expect($item->isAssociatedWith(stdClass::class))->toBeTrue()
        ->and($item->isAssociatedWith('App\\Models\\Product'))->toBeFalse();

    $itemWithoutModel = new CartItem(
        id: 'product-2',
        name: 'Test Product 2',
        price: 49.99,
        quantity: 1
    );

    expect($itemWithoutModel->isAssociatedWith(stdClass::class))->toBeFalse();
});

it('can calculate price with conditions', function (): void {
    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 100.0,
        quantity: 1,
        conditions: [$this->condition1] // -10%
    );

    expect($item->getRawPrice())->toBe(90.0) // Raw price with conditions applied
        ->and($item->getRawPriceWithoutConditions())->toBe(100.0); // Price without conditions
});

it('can calculate price sum with conditions', function (): void {
    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 100.0,
        quantity: 2,
        conditions: [$this->condition1] // -10%
    );

    expect($item->getRawSubtotal())->toBe(180.0) // Raw subtotal with conditions applied
        ->and($item->getRawSubtotalWithoutConditions())->toBe(200.0); // Subtotal without conditions
});

it('can calculate discount amount', function (): void {
    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 100.0,
        quantity: 2,
        conditions: [$this->condition1] // -10%
    );

    expect($item->getDiscountAmount()->getAmount())->toBe(20.0); // 200 - 180 = 20
});

it('can convert to array', function (): void {
    $model = new stdClass;
    $model->id = 123;

    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 100.0,
        quantity: 2,
        attributes: ['color' => 'red'],
        conditions: [$this->condition1],
        associatedModel: $model
    );

    $array = $item->toArray();

    expect($array)->toBeArray()
        ->and($array['id'])->toBe('product-1')
        ->and($array['name'])->toBe('Test Product')
        ->and($array['price'])->toBe(100.0) // Raw price (not calculated)
        ->and($array['quantity'])->toBe(2)
        ->and($array['attributes'])->toBe(['color' => 'red'])
        ->and($array['conditions'])->toBeArray()
        ->and($array['associated_model'])->toBeArray(); // Model gets serialized to array

    // Verify legacy fields are no longer present
    expect($array)->not->toHaveKeys([
        'price_sum',
        'price_without_conditions',
        'price_sum_without_conditions',
        'subtotal_without_conditions',
        'discount_amount',
    ]);

    // But calculated values are still accessible via methods
    expect($item->getSubtotal()->getAmount())->toBe(180.0); // (100 - 10%) * 2 = 180
    expect($item->getPriceWithoutConditions()->getAmount())->toBe(100.0); // Original price
    expect($item->getSubtotalWithoutConditions()->getAmount())->toBe(200.0); // Original total
    expect($item->getDiscountAmount()->getAmount())->toBe(20.0);
});

it('can set new quantity', function (): void {
    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 99.99,
        quantity: 2
    );

    $newItem = $item->setQuantity(5);

    expect($newItem->quantity)->toBe(5)
        ->and($newItem->id)->toBe('product-1') // Other properties preserved
        ->and($item->quantity)->toBe(2); // Original item unchanged
});

it('can add and remove attributes', function (): void {
    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 99.99,
        quantity: 1
    );

    $itemWithAttribute = $item->addAttribute('color', 'red');
    expect($itemWithAttribute->getAttribute('color'))->toBe('red')
        ->and($itemWithAttribute->hasAttribute('color'))->toBeTrue();

    $itemWithoutAttribute = $itemWithAttribute->removeAttribute('color');
    expect($itemWithoutAttribute->hasAttribute('color'))->toBeFalse();
});

it('can add and remove conditions', function (): void {
    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 100.0,
        quantity: 1
    );

    $itemWithCondition = $item->addCondition($this->condition1);
    expect($itemWithCondition->hasCondition('discount-10'))->toBeTrue()
        ->and($itemWithCondition->getCondition('discount-10'))->toBe($this->condition1);

    $itemWithoutCondition = $itemWithCondition->removeCondition('discount-10');
    expect($itemWithoutCondition->hasCondition('discount-10'))->toBeFalse();
});

it('can check equality between items', function (): void {
    $item1 = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 100.0,
        quantity: 1
    );

    $item2 = new CartItem(
        id: 'product-1',
        name: 'Different Name',
        price: 200.0,
        quantity: 5
    );

    $item3 = new CartItem(
        id: 'product-2',
        name: 'Test Product',
        price: 100.0,
        quantity: 1
    );

    expect($item1->equals($item2))->toBeTrue();
    expect($item1->equals($item3))->toBeFalse();
});

it('can create modified copy with with() method', function (): void {
    $item = new CartItem(
        id: 'product-1',
        name: 'Original Product',
        price: 100.0,
        quantity: 1
    );

    $modified = $item->with([
        'name' => 'Modified Product',
        'quantity' => 5,
    ]);

    expect($modified->id)->toBe('product-1');
    expect($modified->name)->toBe('Modified Product');
    expect($modified->price)->toBe(100.0);
    expect($modified->quantity)->toBe(5);
    expect($item->name)->toBe('Original Product');
});

it('sanitizes string prices with currency symbols', function (): void {
    $item1 = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: '$100.50',
        quantity: 1
    );

    expect($item1->price)->toBe(100.50);

    $item2 = new CartItem(
        id: 'product-2',
        name: 'Test Product',
        price: '€50.99',
        quantity: 1
    );

    expect($item2->price)->toBe(50.99);

    $item3 = new CartItem(
        id: 'product-3',
        name: 'Test Product',
        price: '£1,000.00',
        quantity: 1
    );

    expect($item3->price)->toBe(1000.00);
});

it('sanitizes string prices with various currencies', function (): void {
    $yenItem = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: '¥1000',
        quantity: 1
    );

    expect($yenItem->price)->toBe(1000);

    $rupeeItem = new CartItem(
        id: 'product-2',
        name: 'Test Product',
        price: '₹500.50',
        quantity: 1
    );

    expect($rupeeItem->price)->toBe(500.50);

    $ringgitItem = new CartItem(
        id: 'product-3',
        name: 'Test Product',
        price: 'RM 250',
        quantity: 1
    );

    expect($ringgitItem->price)->toBe(250);
});

it('handles integer prices from string without decimal', function (): void {
    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: '$100',
        quantity: 1
    );

    expect($item->price)->toBe(100);
    expect($item->price)->toBeInt();
});

it('handles float prices from string with decimal', function (): void {
    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: '$100.00',
        quantity: 1
    );

    expect($item->price)->toBe(100.0);
    expect($item->price)->toBeFloat();
});

it('validates required fields on creation', function (): void {
    expect(fn () => new CartItem(
        id: '',
        name: 'Test Product',
        price: 99.99,
        quantity: 1
    ))->toThrow(AIArmada\Cart\Exceptions\InvalidCartItemException::class);

    expect(fn () => new CartItem(
        id: 'product-1',
        name: '',
        price: 99.99,
        quantity: 1
    ))->toThrow(AIArmada\Cart\Exceptions\InvalidCartItemException::class);

    expect(fn () => new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: -10.0,
        quantity: 1
    ))->toThrow(AIArmada\Cart\Exceptions\InvalidCartItemException::class);

    expect(fn () => new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 99.99,
        quantity: -1
    ))->toThrow(AIArmada\Cart\Exceptions\InvalidCartItemException::class);
});

it('can convert to JSON', function (): void {
    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 99.99,
        quantity: 2
    );

    $json = $item->toJson();
    $decoded = json_decode($json, true);

    expect($decoded)->toBeArray()
        ->and($decoded['id'])->toBe('product-1')
        ->and($decoded['name'])->toBe('Test Product');
});

it('can be JSON serialized', function (): void {
    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 99.99,
        quantity: 2
    );

    $json = json_encode($item);
    $decoded = json_decode($json, true);

    expect($decoded)->toBeArray()
        ->and($decoded['id'])->toBe('product-1')
        ->and($decoded['name'])->toBe('Test Product');
});

it('can be converted to string', function (): void {
    $item = new CartItem(
        id: 'product-123',
        name: 'Test Product',
        price: 99.99,
        quantity: 2
    );

    $string = (string) $item;

    expect($string)->toBeString()
        ->and($string)->toContain('Test Product')
        ->and($string)->toContain('product-123')
        ->and($string)->toContain('99.99')
        ->and($string)->toContain('2');
});

it('handles complex conditions correctly', function (): void {
    $discountCondition = new CartCondition(
        name: 'discount-20',
        type: 'discount',
        target: 'subtotal',
        value: '-20%'
    );

    $chargeCondition = new CartCondition(
        name: 'handling',
        type: 'charge',
        target: 'subtotal',
        value: '+5'
    );

    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 100.0,
        quantity: 2,
        conditions: [$discountCondition, $chargeCondition]
    );

    // Original: 200.0
    // Price after 20% discount: 80.0
    // Price after $5 charge: 85.0
    // Sum with conditions: 85.0 * 2 = 170.0
    expect($item->getRawSubtotal())->toBe(170.0) // Raw subtotal with conditions
        ->and($item->getDiscountAmount()->getAmount())->toBe(30.0); // 200 - 170
});

it('can set item name', function (): void {
    $item = new CartItem(
        id: 'product-1',
        name: 'Original Name',
        price: 100.0,
        quantity: 2
    );

    $newItem = $item->setName('New Name');

    expect($newItem->name)->toBe('New Name')
        ->and($newItem->id)->toBe($item->id)
        ->and($newItem->price)->toBe($item->price)
        ->and($newItem->quantity)->toBe($item->quantity)
        ->and($newItem)->not->toBe($item); // Should be new instance
});

it('validates name when setting', function (): void {
    $item = new CartItem(
        id: 'product-1',
        name: 'Original Name',
        price: 100.0,
        quantity: 2
    );

    expect(fn () => $item->setName(''))->toThrow(AIArmada\Cart\Exceptions\InvalidCartItemException::class)
        ->and(fn () => $item->setName('   '))->toThrow(AIArmada\Cart\Exceptions\InvalidCartItemException::class);
});

it('can set item price', function (): void {
    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 100.0,
        quantity: 2
    );

    $newItem = $item->setPrice(150.0);

    expect($newItem->price)->toBe(150.0)
        ->and($newItem->id)->toBe($item->id)
        ->and($newItem->name)->toBe($item->name)
        ->and($newItem->quantity)->toBe($item->quantity)
        ->and($newItem)->not->toBe($item); // Should be new instance
});

it('validates price when setting', function (): void {
    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 100.0,
        quantity: 2
    );

    expect(fn () => $item->setPrice(-10.0))->toThrow(AIArmada\Cart\Exceptions\InvalidCartItemException::class);
});

it('validates quantity when setting', function (): void {
    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 100.0,
        quantity: 2
    );

    expect(fn () => $item->setQuantity(-1))->toThrow(AIArmada\Cart\Exceptions\InvalidCartItemException::class);
});

it('can set item attributes', function (): void {
    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 100.0,
        quantity: 2,
        attributes: ['color' => 'red']
    );

    $newAttributes = ['color' => 'blue', 'size' => 'large'];
    $newItem = $item->setAttributes($newAttributes);

    expect($newItem->attributes->toArray())->toBe($newAttributes)
        ->and($newItem->getAttribute('color'))->toBe('blue')
        ->and($newItem->getAttribute('size'))->toBe('large')
        ->and($newItem)->not->toBe($item); // Should be new instance
});

it('can update individual attributes', function (): void {
    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 100.0,
        quantity: 2,
        attributes: ['color' => 'red', 'size' => 'small']
    );

    $newItem = $item->addAttribute('color', 'blue');

    expect($newItem->getAttribute('color'))->toBe('blue')
        ->and($newItem->getAttribute('size'))->toBe('small')
        ->and($newItem)->not->toBe($item); // Should be new instance
});

it('can add new attributes via addAttribute', function (): void {
    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 100.0,
        quantity: 2,
        attributes: ['color' => 'red']
    );

    $newItem = $item->addAttribute('size', 'large');

    expect($newItem->getAttribute('color'))->toBe('red')
        ->and($newItem->getAttribute('size'))->toBe('large')
        ->and($newItem->attributes->count())->toBe(2);
});

it('can remove attributes', function (): void {
    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 100.0,
        quantity: 2,
        attributes: ['color' => 'red', 'size' => 'small']
    );

    $newItem = $item->removeAttribute('size');

    expect($newItem->hasAttribute('color'))->toBeTrue()
        ->and($newItem->hasAttribute('size'))->toBeFalse()
        ->and($newItem->attributes->count())->toBe(1);
});

it('handles removing non-existent attributes gracefully', function (): void {
    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 100.0,
        quantity: 2,
        attributes: ['color' => 'red']
    );

    $newItem = $item->removeAttribute('non-existent');

    expect($newItem->attributes->toArray())->toBe($item->attributes->toArray());
});

it('can check if associated with model class', function (): void {
    $modelInstance = new stdClass;
    $modelInstance->id = 123;

    $itemWithStringModel = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 100.0,
        quantity: 2,
        associatedModel: 'stdClass' // Use existing class
    );

    $itemWithObjectModel = new CartItem(
        id: 'product-2',
        name: 'Test Product 2',
        price: 100.0,
        quantity: 2,
        associatedModel: $modelInstance // Use object instance
    );

    $itemWithoutModel = new CartItem(
        id: 'product-3',
        name: 'Test Product 3',
        price: 100.0,
        quantity: 2
    );

    expect($itemWithStringModel->isAssociatedWith('stdClass'))->toBeTrue()
        ->and($itemWithStringModel->isAssociatedWith('SomeOtherClass'))->toBeFalse()
        ->and($itemWithObjectModel->isAssociatedWith('stdClass'))->toBeTrue()
        ->and($itemWithoutModel->isAssociatedWith('stdClass'))->toBeFalse();
});

it('can get associated model', function (): void {
    $modelInstance = new stdClass;
    $modelInstance->id = 123;

    $item = new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 100.0,
        quantity: 2,
        associatedModel: $modelInstance
    );

    expect($item->getAssociatedModel())->toBe($modelInstance);
});

it('throws exception when creating item with non-existent model class', function (): void {
    expect(fn () => new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 100.0,
        quantity: 2,
        associatedModel: 'NonExistentClass'
    ))->toThrow(AIArmada\Cart\Exceptions\UnknownModelException::class);
});

it('throws exception when attributes exceed max data size', function (): void {
    config()->set('cart.limits.max_data_size_bytes', 100); // Very small limit

    $largeAttributes = [];
    for ($i = 0; $i < 50; $i++) {
        $largeAttributes["key_{$i}"] = str_repeat('x', 100);
    }

    expect(fn () => new CartItem(
        id: 'product-1',
        name: 'Test Product',
        price: 100.0,
        quantity: 2,
        attributes: $largeAttributes
    ))->toThrow(AIArmada\Cart\Exceptions\InvalidCartItemException::class, 'data size');
});

describe('CartItem Condition Normalization', function (): void {
    it('normalizes CartCondition array to collection', function (): void {
        $condition1 = new CartCondition(
            name: 'discount',
            type: 'discount',
            target: 'item',
            value: '-10%'
        );

        $condition2 = new CartCondition(
            name: 'tax',
            type: 'tax',
            target: 'item',
            value: '+5%'
        );

        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 100.0,
            quantity: 1,
            conditions: [$condition1, $condition2]
        );

        expect($item->getConditions()->count())->toBe(2);
        expect($item->hasCondition('discount'))->toBeTrue();
        expect($item->hasCondition('tax'))->toBeTrue();
    });

    it('normalizes array-based condition definition', function (): void {
        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 100.0,
            quantity: 1,
            conditions: [
                'discount' => [
                    'name' => 'discount',
                    'type' => 'discount',
                    'target' => 'item',
                    'value' => '-10%',
                ],
            ]
        );

        expect($item->getConditions()->count())->toBe(1);
        expect($item->hasCondition('discount'))->toBeTrue();
    });

    it('normalizes Collection to CartConditionCollection', function (): void {
        $condition = new CartCondition(
            name: 'discount',
            type: 'discount',
            target: 'item',
            value: '-15%'
        );

        $collection = new Illuminate\Support\Collection([
            'discount' => $condition,
        ]);

        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 100.0,
            quantity: 1,
            conditions: $collection
        );

        expect($item->getConditions())->toBeInstanceOf(AIArmada\Cart\Collections\CartConditionCollection::class);
        expect($item->getConditions()->count())->toBe(1);
        expect($item->hasCondition('discount'))->toBeTrue();
    });

    it('handles CartConditionCollection directly', function (): void {
        $condition = new CartCondition(
            name: 'shipping',
            type: 'shipping',
            target: 'item',
            value: 10.0
        );

        $conditionCollection = new AIArmada\Cart\Collections\CartConditionCollection;
        $conditionCollection->put('shipping', $condition);

        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 100.0,
            quantity: 1,
            conditions: $conditionCollection
        );

        expect($item->getConditions())->toBe($conditionCollection);
        expect($item->getConditions()->count())->toBe(1);
    });

    it('handles mixed condition formats', function (): void {
        $conditionObj = new CartCondition(
            name: 'discount',
            type: 'discount',
            target: 'item',
            value: '-10%'
        );

        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 100.0,
            quantity: 1,
            conditions: [
                'discount' => $conditionObj,
                'tax' => [
                    'name' => 'tax',
                    'type' => 'tax',
                    'target' => 'item',
                    'value' => '+5%',
                ],
            ]
        );

        expect($item->getConditions()->count())->toBe(2);
        expect($item->hasCondition('discount'))->toBeTrue();
        expect($item->hasCondition('tax'))->toBeTrue();
    });

    it('handles empty conditions array', function (): void {
        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 100.0,
            quantity: 1,
            conditions: []
        );

        expect($item->getConditions()->count())->toBe(0);
        expect($item->hasConditions())->toBeFalse();
    });

    it('skips invalid condition entries in array', function (): void {
        $validCondition = new CartCondition(
            name: 'discount',
            type: 'discount',
            target: 'item',
            value: '-10%'
        );

        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 100.0,
            quantity: 1,
            conditions: [
                'discount' => $validCondition,
                'invalid' => 'not a condition',
                'another_invalid' => 12345,
            ]
        );

        expect($item->getConditions()->count())->toBe(1);
        expect($item->hasCondition('discount'))->toBeTrue();
    });

    it('handles CartConditionCollection directly without transformation', function (): void {
        $condition = new CartCondition(
            name: 'premium-discount',
            type: 'discount',
            target: 'item',
            value: '-25%'
        );

        $conditionCollection = new AIArmada\Cart\Collections\CartConditionCollection;
        $conditionCollection->put('premium-discount', $condition);

        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 100.0,
            quantity: 1,
            conditions: $conditionCollection
        );

        expect($item->getConditions())->toBe($conditionCollection);
        expect($item->getConditions()->count())->toBe(1);
        expect($item->hasCondition('premium-discount'))->toBeTrue();
    });

    it('uses condition name as key when normalizing CartCondition objects in array', function (): void {
        $condition = new CartCondition(
            name: 'auto-keyed-discount',
            type: 'discount',
            target: 'item',
            value: '-15%'
        );

        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 100.0,
            quantity: 1,
            conditions: [0 => $condition] // Numeric key, should use condition name instead
        );

        expect($item->getConditions()->count())->toBe(1);
        expect($item->getConditions()->has('auto-keyed-discount'))->toBeTrue();
    });

    it('uses condition name as key when normalizing from Collection', function (): void {
        $condition = new CartCondition(
            name: 'collection-discount',
            type: 'discount',
            target: 'item',
            value: '-20%'
        );

        $collection = new Illuminate\Support\Collection([
            'some-key' => $condition,
        ]);

        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 100.0,
            quantity: 1,
            conditions: $collection
        );

        expect($item->getConditions()->count())->toBe(1);
        expect($item->getConditions()->has('collection-discount'))->toBeTrue();
    });

    it('clears all conditions from an item', function (): void {
        $condition1 = new CartCondition(
            name: 'discount-10',
            type: 'discount',
            target: 'item',
            value: '-10%'
        );

        $condition2 = new CartCondition(
            name: 'tax',
            type: 'tax',
            target: 'item',
            value: '+5%'
        );

        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 100.0,
            quantity: 1,
            conditions: [$condition1, $condition2]
        );

        expect($item->hasConditions())->toBeTrue();
        expect($item->getConditions()->count())->toBe(2);

        $clearedItem = $item->clearConditions();

        expect($clearedItem->hasConditions())->toBeFalse();
        expect($clearedItem->getConditions()->count())->toBe(0);
        expect($clearedItem)->not->toBe($item); // Should be new instance
    });

    it('checks if item has specific condition by name', function (): void {
        $condition = new CartCondition(
            name: 'vip-discount',
            type: 'discount',
            target: 'item',
            value: '-15%'
        );

        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 100.0,
            quantity: 1,
            conditions: [$condition]
        );

        expect($item->hasCondition('vip-discount'))->toBeTrue();
        expect($item->hasCondition('non-existent'))->toBeFalse();
    });

    it('normalizes array with both CartCondition objects and array definitions', function (): void {
        $objectCondition = new CartCondition(
            name: 'object-discount',
            type: 'discount',
            target: 'item',
            value: '-10%'
        );

        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 100.0,
            quantity: 1,
            conditions: [
                'object' => $objectCondition,
                'array' => [
                    'name' => 'array-discount',
                    'type' => 'discount',
                    'target' => 'item',
                    'value' => '-5%',
                ],
            ]
        );

        expect($item->getConditions()->count())->toBe(2);
        expect($item->hasCondition('object-discount'))->toBeTrue();
        // The key from the array becomes the condition key, not the name
        expect($item->hasCondition('array'))->toBeTrue();
        expect($item->getCondition('object-discount'))->toBeInstanceOf(CartCondition::class);
        expect($item->getCondition('array'))->toBeInstanceOf(CartCondition::class);
        expect($item->getCondition('array')->getName())->toBe('array-discount');
    });

    it('normalizes Collection of CartCondition objects', function (): void {
        $condition1 = new CartCondition(
            name: 'first-discount',
            type: 'discount',
            target: 'item',
            value: '-10%'
        );

        $condition2 = new CartCondition(
            name: 'second-discount',
            type: 'discount',
            target: 'item',
            value: '-5%'
        );

        $collection = new Illuminate\Support\Collection([
            'first' => $condition1,
            'second' => $condition2,
        ]);

        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 100.0,
            quantity: 1,
            conditions: $collection
        );

        expect($item->getConditions()->count())->toBe(2);
        // Keys should be auto-generated from condition names, not from collection keys
        expect($item->hasCondition('first-discount'))->toBeTrue();
        expect($item->hasCondition('second-discount'))->toBeTrue();
    });
});

describe('CartItem Money Methods', function (): void {
    it('provides subtotal() as alias for getSubtotal()', function (): void {
        $item = new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 100.0,
            quantity: 2
        );

        $subtotal = $item->subtotal();
        $getSubtotal = $item->getSubtotal();

        expect($subtotal)->toEqual($getSubtotal);
        expect($subtotal)->toBeInstanceOf(Akaunting\Money\Money::class);
    });

    it('provides total() as alias for getSubtotal()', function (): void {
        $item = new CartItem(
            id: 'product-2',
            name: 'Test Product 2',
            price: 50.0,
            quantity: 3
        );

        $total = $item->total();
        $getSubtotal = $item->getSubtotal();

        expect($total)->toEqual($getSubtotal);
        expect($total->getAmount())->toBe(150.0);
    });

    it('provides discountAmount() as alias for getDiscountAmount()', function (): void {
        $item = new CartItem(
            id: 'product-3',
            name: 'Test Product 3',
            price: 200.0,
            quantity: 1,
            conditions: [
                new CartCondition(
                    name: 'discount',
                    type: 'discount',
                    target: 'item',
                    value: '-20%'
                )
            ]
        );

        $discountAmount = $item->discountAmount();
        $getDiscountAmount = $item->getDiscountAmount();

        expect($discountAmount)->toEqual($getDiscountAmount);
        expect($discountAmount->getAmount())->toBe(40.0); // 20% of 200
    });

    it('validates quantity does not exceed maximum limit', function (): void {
        $maxQuantity = config('cart.limits.max_item_quantity', 10000);

        expect(fn () => new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 100.0,
            quantity: $maxQuantity + 1
        ))->toThrow(AIArmada\Cart\Exceptions\InvalidCartItemException::class, 'quantity cannot exceed');
    });

    it('validates string length limits for id and name', function (): void {
        $maxLength = config('cart.limits.max_string_length', 255);
        $longString = str_repeat('a', $maxLength + 1);

        expect(fn () => new CartItem(
            id: $longString,
            name: 'Test Product',
            price: 100.0,
            quantity: 1
        ))->toThrow(AIArmada\Cart\Exceptions\InvalidCartItemException::class, 'ID cannot exceed');

        expect(fn () => new CartItem(
            id: 'product-1',
            name: $longString,
            price: 100.0,
            quantity: 1
        ))->toThrow(AIArmada\Cart\Exceptions\InvalidCartItemException::class, 'name cannot exceed');
    });

    it('handles JSON encoding errors in data size validation', function (): void {
        // Create a string with invalid UTF-8 sequence to trigger json_encode error
        $invalidUtf8 = "\xB1\x31";

        expect(fn () => new CartItem(
            id: 'product-1',
            name: 'Test Product',
            price: 100.0,
            quantity: 1,
            attributes: ['bad' => $invalidUtf8]
        ))->toThrow(AIArmada\Cart\Exceptions\InvalidCartItemException::class, 'Cannot validate');
    });
});
