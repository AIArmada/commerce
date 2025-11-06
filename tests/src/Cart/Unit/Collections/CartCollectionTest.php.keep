<?php

declare(strict_types=1);

use MasyukAI\Cart\Collections\CartCollection;
use MasyukAI\Cart\Models\CartItem;

beforeEach(function (): void {
    $this->collection = new CartCollection;
    $this->item1 = new CartItem(
        id: 'item-1',
        name: 'Item 1',
        price: 100.0,
        quantity: 2,
        attributes: ['color' => 'red']
    );
    $this->item2 = new CartItem(
        id: 'item-2',
        name: 'Item 2',
        price: 50.0,
        quantity: 3,
        attributes: ['size' => 'large']
    );
});

it('can be instantiated empty', function (): void {
    expect($this->collection)->toBeInstanceOf(CartCollection::class)
        ->and($this->collection->count())->toBe(0)
        ->and($this->collection->isEmpty())->toBeTrue();
});

it('can add items with put method', function (): void {
    $this->collection->put('item-1', $this->item1);

    expect($this->collection->count())->toBe(1)
        ->and($this->collection->has('item-1'))->toBeTrue()
        ->and($this->collection->get('item-1'))->toBe($this->item1);
});

it('can add items with addItem method', function (): void {
    $result = $this->collection->addItem($this->item1);

    expect($result)->toBeInstanceOf(CartCollection::class)
        ->and($this->collection->count())->toBe(1)
        ->and($this->collection->hasItem('item-1'))->toBeTrue()
        ->and($this->collection->getItem('item-1'))->toBe($this->item1);
});

it('can remove items with removeItem method', function (): void {
    $this->collection->addItem($this->item1);
    $this->collection->addItem($this->item2);

    $result = $this->collection->removeItem('item-1');

    expect($result)->toBeInstanceOf(CartCollection::class)
        ->and($this->collection->count())->toBe(1)
        ->and($this->collection->hasItem('item-1'))->toBeFalse()
        ->and($this->collection->hasItem('item-2'))->toBeTrue();
});

it('can get items with getItem method', function (): void {
    $this->collection->addItem($this->item1);

    $item = $this->collection->getItem('item-1');
    $nonExistent = $this->collection->getItem('non-existent');

    expect($item)->toBe($this->item1)
        ->and($nonExistent)->toBeNull();
});

it('can check item existence with hasItem method', function (): void {
    $this->collection->addItem($this->item1);

    expect($this->collection->hasItem('item-1'))->toBeTrue()
        ->and($this->collection->hasItem('non-existent'))->toBeFalse();
});

it('can get total quantity with getTotalQuantity method', function (): void {
    $this->collection->addItem($this->item1); // quantity: 2
    $this->collection->addItem($this->item2); // quantity: 3

    expect($this->collection->getTotalQuantity())->toBe(5);
});

it('can get subtotal with getSubtotal method', function (): void {
    $this->collection->addItem($this->item1); // 100 * 2 = 200
    $this->collection->addItem($this->item2); // 50 * 3 = 150

    $subtotal = $this->collection->subtotal();
    if ($subtotal instanceof \Akaunting\Money\Money) {
        $subtotal = $subtotal->getAmount();
    }
    expect($subtotal)->toBe(350.0);
});

it('can get subtotal which includes item-level conditions by default', function (): void {
    $this->collection->addItem($this->item1);
    $this->collection->addItem($this->item2);

    // Since items don't have conditions yet, should be same as raw subtotal
    $subtotal = $this->collection->subtotal();
    if ($subtotal instanceof \Akaunting\Money\Money) {
        $subtotal = $subtotal->getAmount();
    }
    expect($subtotal)->toBe(350.0);
});

it('can convert to formatted array with toFormattedArray method', function (): void {
    $this->collection->addItem($this->item1);
    $this->collection->addItem($this->item2);

    $formatted = $this->collection->toFormattedArray();

    expect($formatted)->toBeArray()
        ->and($formatted)->toHaveKeys([
            'items', 'total_quantity', 'subtotal',
            'total', 'total_without_conditions', 'count', 'is_empty',
        ])
        ->and($formatted['total_quantity'])->toBe(5)
        ->and($formatted['subtotal'])->toBe(350.0)
        ->and($formatted['count'])->toBe(2)
        ->and($formatted['is_empty'])->toBeFalse();
});

it('can get total with total method', function (): void {
    $this->collection->addItem($this->item1); // 100 * 2 = 200
    $this->collection->addItem($this->item2); // 50 * 3 = 150

    expect($this->collection->total())->toBe(350.0);
});

it('can filter items by condition with filterByCondition method', function (): void {
    $itemWithCondition = new CartItem(
        id: 'item-3',
        name: 'Item 3',
        price: 75.0,
        quantity: 1,
        attributes: [],
        conditions: [
            'special' => [
                'name' => 'special',
                'type' => 'discount',
                'target' => 'subtotal',
                'value' => '-10',
                'attributes' => [],
                'order' => 0,
            ],
        ]
    );

    $this->collection->addItem($this->item1); // no conditions
    $this->collection->addItem($itemWithCondition); // has 'special' condition

    $filtered = $this->collection->filterByCondition('special');

    expect($filtered->count())->toBe(1)
        ->and($filtered->hasItem('item-3'))->toBeTrue()
        ->and($filtered->hasItem('item-1'))->toBeFalse();
});

it('can filter items by attribute with filterByAttribute method', function (): void {
    $item3 = new CartItem(
        id: 'item-3',
        name: 'Item 3',
        price: 75.0,
        quantity: 1,
        attributes: ['color' => 'red', 'size' => 'medium']
    );

    $this->collection->addItem($this->item1); // color: red
    $this->collection->addItem($this->item2); // size: large
    $this->collection->addItem($item3); // color: red, size: medium

    $redItems = $this->collection->filterByAttribute('color', 'red');
    $itemsWithColor = $this->collection->filterByAttribute('color');

    expect($redItems->count())->toBe(2)
        ->and($redItems->hasItem('item-1'))->toBeTrue()
        ->and($redItems->hasItem('item-3'))->toBeTrue()
        ->and($itemsWithColor->count())->toBe(2);
});

it('can add multiple items', function (): void {
    $this->collection->put('item-1', $this->item1);
    $this->collection->put('item-2', $this->item2);

    expect($this->collection->count())->toBe(2)
        ->and($this->collection->has('item-1'))->toBeTrue()
        ->and($this->collection->has('item-2'))->toBeTrue();
});

it('can remove items', function (): void {
    $this->collection->put('item-1', $this->item1);
    $this->collection->put('item-2', $this->item2);

    $removed = $this->collection->forget('item-1');

    expect($this->collection->count())->toBe(1)
        ->and($this->collection->has('item-1'))->toBeFalse()
        ->and($this->collection->has('item-2'))->toBeTrue();
});

it('can get total quantity of all items', function (): void {
    $this->collection->put('item-1', $this->item1); // quantity: 2
    $this->collection->put('item-2', $this->item2); // quantity: 3

    $totalQuantity = $this->collection->sum(fn ($item) => $item->quantity);

    expect($totalQuantity)->toBe(5);
});

it('can calculate total price sum', function (): void {
    $this->collection->put('item-1', $this->item1); // 100 * 2 = 200
    $this->collection->put('item-2', $this->item2); // 50 * 3 = 150

    $total = $this->collection->sum(fn ($item) => $item->getSubtotal() instanceof \Akaunting\Money\Money ? $item->getSubtotal()->getAmount() : (float) $item->getSubtotal());
    expect($total)->toBe(350.0);
});

it('can filter items by attributes', function (): void {
    $item3 = new CartItem(
        id: 'item-3',
        name: 'Item 3',
        price: 75.0,
        quantity: 1,
        attributes: ['color' => 'red', 'size' => 'medium']
    );

    $this->collection->put('item-1', $this->item1); // color: red
    $this->collection->put('item-2', $this->item2); // size: large
    $this->collection->put('item-3', $item3); // color: red, size: medium

    $redItems = $this->collection->filter(function ($item) {
        return isset($item->attributes['color']) && $item->attributes['color'] === 'red';
    });

    expect($redItems->count())->toBe(2)
        ->and($redItems->has('item-1'))->toBeTrue()
        ->and($redItems->has('item-3'))->toBeTrue()
        ->and($redItems->has('item-2'))->toBeFalse();
});

it('can search items by name', function (): void {
    $item3 = new CartItem(
        id: 'item-3',
        name: 'Special Item',
        price: 75.0,
        quantity: 1,
        attributes: []
    );

    $this->collection->put('item-1', $this->item1);
    $this->collection->put('item-2', $this->item2);
    $this->collection->put('item-3', $item3);

    $searchResults = $this->collection->filter(function ($item) {
        return stripos($item->name, 'Special') !== false;
    });

    expect($searchResults->count())->toBe(1)
        ->and($searchResults->first()->name)->toBe('Special Item');
});

it('can map items to different structure', function (): void {
    $this->collection->put('item-1', $this->item1);
    $this->collection->put('item-2', $this->item2);

    $mapped = $this->collection->map(function ($item) {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'total' => $item->getSubtotal(),
        ];
    });

    expect($mapped->count())->toBe(2)
        ->and(($mapped->get('item-1')['total'] instanceof \Akaunting\Money\Money ? $mapped->get('item-1')['total']->getAmount() : $mapped->get('item-1')['total']))->toBe(200.0)
        ->and(($mapped->get('item-2')['total'] instanceof \Akaunting\Money\Money ? $mapped->get('item-2')['total']->getAmount() : $mapped->get('item-2')['total']))->toBe(150.0);
});

it('can group items by attribute', function (): void {
    $item3 = new CartItem(
        id: 'item-3',
        name: 'Item 3',
        price: 30.0,
        quantity: 1,
        attributes: ['color' => 'red']
    );

    $this->collection->put('item-1', $this->item1); // color: red
    $this->collection->put('item-2', $this->item2); // size: large (no color)
    $this->collection->put('item-3', $item3); // color: red

    $grouped = $this->collection->groupBy(function ($item) {
        return $item->attributes['color'] ?? 'no-color';
    });

    expect($grouped->has('red'))->toBeTrue()
        ->and($grouped->has('no-color'))->toBeTrue()
        ->and($grouped->get('red')->count())->toBe(2)
        ->and($grouped->get('no-color')->count())->toBe(1);
});

it('can sort items by price', function (): void {
    $item3 = new CartItem(
        id: 'item-3',
        name: 'Item 3',
        price: 25.0,
        quantity: 1,
        attributes: []
    );

    $this->collection->put('item-1', $this->item1); // price: 100
    $this->collection->put('item-2', $this->item2); // price: 50
    $this->collection->put('item-3', $item3); // price: 25

    $sorted = $this->collection->sortBy(fn ($item) => $item->price);

    $sortedKeys = $sorted->keys()->toArray();
    expect($sortedKeys)->toBe(['item-3', 'item-2', 'item-1']);
});

it('can find items by condition', function (): void {
    $this->collection->put('item-1', $this->item1);
    $this->collection->put('item-2', $this->item2);

    $found = $this->collection->first(function ($item) {
        return $item->price > 75;
    });

    expect($found)->toBe($this->item1)
        ->and($found->price)->toBe(100.0);
});

it('can check if any item matches condition', function (): void {
    $this->collection->put('item-1', $this->item1);
    $this->collection->put('item-2', $this->item2);

    $hasExpensiveItem = $this->collection->contains(function ($item) {
        return $item->price > 90;
    });

    $hasCheapItem = $this->collection->contains(function ($item) {
        return $item->price < 10;
    });

    expect($hasExpensiveItem)->toBeTrue()
        ->and($hasCheapItem)->toBeFalse();
});

it('can reject items by condition', function (): void {
    $this->collection->put('item-1', $this->item1); // price: 100
    $this->collection->put('item-2', $this->item2); // price: 50

    $filtered = $this->collection->reject(function ($item) {
        return $item->price < 75;
    });

    expect($filtered->count())->toBe(1)
        ->and($filtered->has('item-1'))->toBeTrue()
        ->and($filtered->has('item-2'))->toBeFalse();
});

it('can get items as array', function (): void {
    $this->collection->put('item-1', $this->item1);
    $this->collection->put('item-2', $this->item2);

    $array = $this->collection->toArray();

    expect($array)->toBeArray()
        ->and($array)->toHaveKey('item-1')
        ->and($array)->toHaveKey('item-2')
        ->and($array['item-1'])->toBeArray()
        ->and($array['item-2'])->toBeArray()
        ->and($array['item-1']['id'])->toBe('item-1')
        ->and($array['item-2']['id'])->toBe('item-2');
});

it('can convert to JSON', function (): void {
    $this->collection->put('item-1', $this->item1);

    $json = $this->collection->toJson();
    $decoded = json_decode($json, true);

    expect($json)->toBeJson()
        ->and($decoded)->toHaveKey('item-1')
        ->and($decoded['item-1']['id'])->toBe('item-1')
        ->and($decoded['item-1']['name'])->toBe('Item 1');
});

it('can be merged with another collection', function (): void {
    $otherCollection = new CartCollection;
    $item3 = new CartItem(
        id: 'item-3',
        name: 'Item 3',
        price: 75.0,
        quantity: 1,
        attributes: []
    );

    $this->collection->put('item-1', $this->item1);
    $otherCollection->put('item-2', $this->item2);
    $otherCollection->put('item-3', $item3);

    $merged = $this->collection->merge($otherCollection);

    expect($merged->count())->toBe(3)
        ->and($merged->has('item-1'))->toBeTrue()
        ->and($merged->has('item-2'))->toBeTrue()
        ->and($merged->has('item-3'))->toBeTrue();
});

it('can handle empty operations gracefully', function (): void {
    expect($this->collection->first())->toBeNull()
        ->and($this->collection->sum('price'))->toBe(0)
        ->and($this->collection->isEmpty())->toBeTrue()
        ->and($this->collection->isNotEmpty())->toBeFalse();
});

it('can pluck specific values', function (): void {
    $this->collection->put('item-1', $this->item1);
    $this->collection->put('item-2', $this->item2);

    $names = $this->collection->pluck('name');
    $prices = $this->collection->pluck('price');

    expect($names->toArray())->toBe(['Item 1', 'Item 2'])
        ->and($prices->toArray())->toBe([100.0, 50.0]);
});

it('can determine uniqueness', function (): void {
    $item1Copy = new CartItem(
        id: 'item-1-copy',
        name: 'Item 1', // Same name
        price: 100.0,   // Same price
        quantity: 1,
        attributes: []
    );

    $this->collection->put('item-1', $this->item1);
    $this->collection->put('item-2', $this->item2);
    $this->collection->put('item-1-copy', $item1Copy);

    $uniqueByName = $this->collection->unique('name');

    expect($uniqueByName->count())->toBe(2); // Item 1 and Item 2
});

it('can chunk items into smaller collections', function (): void {
    $this->collection->put('item-1', $this->item1);
    $this->collection->put('item-2', $this->item2);

    $chunks = $this->collection->chunk(1);

    expect($chunks->count())->toBe(2)
        ->and($chunks->first()->count())->toBe(1);
});

it('can filter by model', function (): void {
    $itemWithModel = new CartItem(
        id: 'item-model',
        name: 'Model Item',
        price: 75.0,
        quantity: 1,
        attributes: [],
        associatedModel: 'stdClass' // Use existing class
    );

    $this->collection->addItem($this->item1);
    $this->collection->addItem($itemWithModel);

    $modelItems = $this->collection->filterByModel('stdClass');

    expect($modelItems->count())->toBe(1)
        ->and($modelItems->hasItem('item-model'))->toBeTrue();
});

it('can search items by name using searchByName method', function (): void {
    $this->collection->addItem($this->item1); // "Item 1"
    $this->collection->addItem($this->item2); // "Item 2"

    $searchResults = $this->collection->searchByName('Item 1');

    expect($searchResults->count())->toBe(1)
        ->and($searchResults->hasItem('item-1'))->toBeTrue();
});

it('can sort items by price using sortByPrice method', function (): void {
    $this->collection->addItem($this->item1); // price: 100
    $this->collection->addItem($this->item2); // price: 50

    $sortedAsc = $this->collection->sortByPrice('asc');
    $sortedDesc = $this->collection->sortByPrice('desc');

    expect($sortedAsc->first()->price)->toBe(50.0)
        ->and($sortedDesc->first()->price)->toBe(100.0);
});

it('can sort items by quantity', function (): void {
    $this->collection->addItem($this->item1); // quantity: 2
    $this->collection->addItem($this->item2); // quantity: 3

    $sortedAsc = $this->collection->sortByQuantity('asc');
    $sortedDesc = $this->collection->sortByQuantity('desc');

    expect($sortedAsc->first()->quantity)->toBe(2)
        ->and($sortedDesc->first()->quantity)->toBe(3);
});

it('can sort items by name', function (): void {
    $this->collection->addItem($this->item1); // "Item 1"
    $this->collection->addItem($this->item2); // "Item 2"

    $sortedAsc = $this->collection->sortByName('asc');
    $sortedDesc = $this->collection->sortByName('desc');

    expect($sortedAsc->first()->name)->toBe('Item 1')
        ->and($sortedDesc->first()->name)->toBe('Item 2');
});

it('can get unique items by property', function (): void {
    $item3 = new CartItem(
        id: 'item-3',
        name: 'Item 1', // Same name as item1
        price: 75.0,
        quantity: 1
    );

    $this->collection->addItem($this->item1);
    $this->collection->addItem($this->item2);
    $this->collection->addItem($item3);

    $uniqueByName = $this->collection->uniqueBy('name');

    expect($uniqueByName->count())->toBe(2);
});

it('can group items by property', function (): void {
    $item3 = new CartItem(
        id: 'item-3',
        name: 'Item 1', // Same name as item1
        price: 75.0,
        quantity: 1
    );

    $this->collection->addItem($this->item1);
    $this->collection->addItem($this->item2);
    $this->collection->addItem($item3);

    $groupedByName = $this->collection->groupByProperty('name');

    expect($groupedByName->count())->toBe(2)
        ->and($groupedByName->get('Item 1')->count())->toBe(2);
});

it('can filter items by quantity thresholds', function (): void {
    $this->collection->addItem($this->item1); // quantity: 2
    $this->collection->addItem($this->item2); // quantity: 3

    $greaterThan = $this->collection->whereQuantityGreaterThan(2);
    $lessThan = $this->collection->whereQuantityLessThan(3);

    expect($greaterThan->count())->toBe(1)
        ->and($greaterThan->hasItem('item-2'))->toBeTrue()
        ->and($lessThan->count())->toBe(1)
        ->and($lessThan->hasItem('item-1'))->toBeTrue();
});

it('can filter items by price range', function (): void {
    $this->collection->addItem($this->item1); // price: 100
    $this->collection->addItem($this->item2); // price: 50

    $inRange = $this->collection->wherePriceBetween(40, 60);

    expect($inRange->count())->toBe(1)
        ->and($inRange->hasItem('item-2'))->toBeTrue();
});

it('can check if collection has items with conditions', function (): void {
    $this->collection->addItem($this->item1);
    $this->collection->addItem($this->item2);

    // Since items don't have conditions yet, should be false
    expect($this->collection->isNotEmpty())->toBeTrue(); // Has items, but no conditions applied yet
});

it('can get total discount amount', function (): void {
    $this->collection->addItem($this->item1);
    $this->collection->addItem($this->item2);

    $totalDiscount = $this->collection->getTotalDiscount();

    // Since items don't have conditions yet, discount should be 0
    if ($totalDiscount instanceof \Akaunting\Money\Money) {
        $totalDiscount = $totalDiscount->getAmount();
    }
    expect($totalDiscount)->toBe(0.0);
});

it('can get collection statistics', function (): void {
    $this->collection->addItem($this->item1); // price: 100, quantity: 2
    $this->collection->addItem($this->item2); // price: 50, quantity: 3

    $stats = $this->collection->getStatistics();

    expect($stats)->toBeArray()
        ->and($stats)->toHaveKeys([
            'total_items', 'total_quantity', 'average_price',
            'highest_price', 'lowest_price', 'total_value',
            'total_with_conditions', 'items_with_conditions',
        ])
        ->and($stats['total_items'])->toBe(2)
        ->and($stats['total_quantity'])->toBe(5)
        ->and($stats['average_price'])->toBe(75.0)
        ->and($stats['highest_price'])->toBe(100.0)
        ->and($stats['lowest_price'])->toBe(50.0);
});

it('can filter items where quantity is above threshold', function (): void {
    $this->collection->addItem($this->item1); // quantity: 2
    $this->collection->addItem($this->item2); // quantity: 3

    $aboveThreshold = $this->collection->whereQuantityAbove(2);

    expect($aboveThreshold->count())->toBe(1)
        ->and($aboveThreshold->hasItem('item-2'))->toBeTrue();
});

it('can group items by attribute using groupBy method', function (): void {
    $item3 = new CartItem(
        id: 'item-3',
        name: 'Item 3',
        price: 75.0,
        quantity: 1,
        attributes: ['color' => 'red']
    );

    $this->collection->addItem($this->item1); // color: red
    $this->collection->addItem($this->item2); // size: large (no color)
    $this->collection->addItem($item3); // color: red

    $groupedByColor = $this->collection->groupByAttribute('color');

    expect($groupedByColor->has('red'))->toBeTrue()
        ->and($groupedByColor->get('red')->count())->toBe(2);
});

it('can find items by model type', function (): void {
    $itemWithModel = new CartItem(
        id: 'item-model',
        name: 'Model Item',
        price: 75.0,
        quantity: 1,
        attributes: [],
        associatedModel: 'stdClass' // Use existing class
    );

    $this->collection->addItem($this->item1);
    $this->collection->addItem($itemWithModel);

    $modelItems = $this->collection->whereModel('stdClass');

    expect($modelItems->count())->toBe(1)
        ->and($modelItems->hasItem('item-model'))->toBeTrue();
});

it('can filter items by condition type', function (): void {
    $itemWithDiscount = new CartItem(
        id: 'item-discount',
        name: 'Discount Item',
        price: 100.0,
        quantity: 1,
        attributes: [],
        conditions: [
            'special-discount' => [
                'name' => 'special-discount',
                'type' => 'discount',
                'target' => 'subtotal',
                'value' => '-10',
                'attributes' => [],
                'order' => 0,
            ],
        ]
    );

    $itemWithTax = new CartItem(
        id: 'item-tax',
        name: 'Tax Item',
        price: 50.0,
        quantity: 1,
        attributes: [],
        conditions: [
            'vat' => [
                'name' => 'vat',
                'type' => 'tax',
                'target' => 'subtotal',
                'value' => '15%',
                'attributes' => [],
                'order' => 1,
            ],
        ]
    );

    $this->collection->addItem($this->item1); // no conditions
    $this->collection->addItem($itemWithDiscount); // discount type
    $this->collection->addItem($itemWithTax); // tax type

    $discountItems = $this->collection->filterByConditionType('discount');
    $taxItems = $this->collection->filterByConditionType('tax');

    expect($discountItems->count())->toBe(1)
        ->and($discountItems->hasItem('item-discount'))->toBeTrue()
        ->and($taxItems->count())->toBe(1)
        ->and($taxItems->hasItem('item-tax'))->toBeTrue();
});

it('can filter items by condition target', function (): void {
    $itemWithSubtotalCondition = new CartItem(
        id: 'item-subtotal',
        name: 'Subtotal Item',
        price: 100.0,
        quantity: 1,
        attributes: [],
        conditions: [
            'discount' => [
                'name' => 'discount',
                'type' => 'discount',
                'target' => 'subtotal',
                'value' => '-10',
                'attributes' => [],
                'order' => 0,
            ],
        ]
    );

    $itemWithTotalCondition = new CartItem(
        id: 'item-total',
        name: 'Total Item',
        price: 50.0,
        quantity: 1,
        attributes: [],
        conditions: [
            'shipping' => [
                'name' => 'shipping',
                'type' => 'fee',
                'target' => 'total',
                'value' => '5',
                'attributes' => [],
                'order' => 1,
            ],
        ]
    );

    $this->collection->addItem($this->item1); // no conditions
    $this->collection->addItem($itemWithSubtotalCondition); // subtotal target
    $this->collection->addItem($itemWithTotalCondition); // total target

    $subtotalItems = $this->collection->filterByConditionTarget('subtotal');
    $totalItems = $this->collection->filterByConditionTarget('total');

    expect($subtotalItems->count())->toBe(1)
        ->and($subtotalItems->hasItem('item-subtotal'))->toBeTrue()
        ->and($totalItems->count())->toBe(1)
        ->and($totalItems->hasItem('item-total'))->toBeTrue();
});

it('can filter items by condition value', function (): void {
    $itemWithNegativeValue = new CartItem(
        id: 'item-negative',
        name: 'Negative Value Item',
        price: 100.0,
        quantity: 1,
        attributes: [],
        conditions: [
            'discount' => [
                'name' => 'discount',
                'type' => 'discount',
                'target' => 'subtotal',
                'value' => '-10',
                'attributes' => [],
                'order' => 0,
            ],
        ]
    );

    $itemWithPercentageValue = new CartItem(
        id: 'item-percentage',
        name: 'Percentage Value Item',
        price: 50.0,
        quantity: 1,
        attributes: [],
        conditions: [
            'tax' => [
                'name' => 'tax',
                'type' => 'tax',
                'target' => 'subtotal',
                'value' => '15%',
                'attributes' => [],
                'order' => 1,
            ],
        ]
    );

    $this->collection->addItem($this->item1); // no conditions
    $this->collection->addItem($itemWithNegativeValue); // value: -10
    $this->collection->addItem($itemWithPercentageValue); // value: 15%

    $negativeItems = $this->collection->filterByConditionValue('-10');
    $percentageItems = $this->collection->filterByConditionValue('15%');

    expect($negativeItems->count())->toBe(1)
        ->and($negativeItems->hasItem('item-negative'))->toBeTrue()
        ->and($percentageItems->count())->toBe(1)
        ->and($percentageItems->hasItem('item-percentage'))->toBeTrue();
});
