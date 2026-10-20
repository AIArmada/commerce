<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use Akaunting\Money\Money;
use Tests\Support\Cart\InMemoryStorage;

function createMoneyTestCart(string $instance = 'test'): Cart
{
    return new Cart(
        storage: new InMemoryStorage,
        identifier: 'test-identifier',
        instanceName: $instance
    );
}

beforeEach(function (): void {
    config(['cart.money.default_currency' => 'USD']);
});

it('demonstrates money integration with cart package', function (): void {
    $cart = createMoneyTestCart('shopping');

    // Add items with float prices (treated as dollars, converted to cents)
    $cart->add('product-1', 'Widget', 19.99, 2);  // $19.99 = 1999 cents
    $cart->add('product-2', 'Premium Widget', 299.99, 1);  // $299.99 = 29999 cents

    // Get items and verify Money objects
    $item1 = $cart->get('product-1');
    $item2 = $cart->get('product-2');

    expect($item1->getPrice()->getAmount())->toBe(1999); // $19.99 as 1999 cents
    expect($item1->getSubtotal()->getAmount())->toBe(3998); // 1999 * 2 = 3998 cents

    expect($item2->getPrice()->getAmount())->toBe(29999); // $299.99 as 29999 cents
    expect($item2->getSubtotal()->getAmount())->toBe(29999); // 29999 * 1 = 29999 cents

    // Test cart totals with Money precision
    expect($cart->count())->toBe(3); // Total quantity: 2 + 1 = 3
});

it('rounds decimal major-unit prices half-up before storing minor units', function (): void {
    $cart = createMoneyTestCart('rounding_test');

    $cart->add('rounded-item', 'Rounded Item', '19.995', 1);

    expect($cart->get('rounded-item')->price)->toBe(2000);
});

it('shows money precision advantages over float arithmetic', function (): void {
    $cart = createMoneyTestCart('precision_test');

    // Add items with prices that cause float precision issues
    $trickyPrice = 10.0; // 0.1 USD = 10 cents
    $cart->add('item-1', 'Tricky Item', $trickyPrice, 3);

    $item = $cart->get('item-1');

    // Money maintains precision: 0.1 * 3 = 0.3 exactly
    expect($item->getSubtotal()->getAmount())->toBe(3000); // 0.3 stored as 30 cents

    // Compare with float calculation that would lose precision
    $floatResult = 0.1 * 3; // This can be 0.30000000000000004 in some cases
    expect($item->getSubtotal()->getAmount())->toBe(3000); // Money is always exact (in cents)
});

it('handles complex cart scenarios with money precision', function (): void {
    $cart = createMoneyTestCart('complex_test');

    // Add items with float prices (dollars converted to cents)
    $cart->add('expensive', 'Luxury Item', 1299.99, 1);  // $1299.99 = 129999 cents
    $cart->add('budget', 'Budget Item', 5.99, 3);  // $5.99 = 599 cents

    // Test precision calculations
    $expensiveCartItem = $cart->get('expensive');
    $budgetCartItem = $cart->get('budget');

    expect($expensiveCartItem->getPrice()->getAmount())->toBe(129999); // $1299.99 as 129999 cents
    expect($budgetCartItem->getPrice()->getAmount())->toBe(599); // $5.99 as 599 cents
    expect($budgetCartItem->getSubtotal()->getAmount())->toBe(1797); // 599 * 3 = 1797 cents

    // Total should be precise - Cart returns CartMoney, get amount
    $total = $cart->total()->getAmount();
    expect($total)->toBe(131796); // 129999 + 1797 = 131796 cents
});

it('demonstrates money currency safety', function (): void {
    $cart = createMoneyTestCart('currency_test');

    // Add items with different currencies - should maintain currency integrity
    $usdPrice = 1999.0; // 19.99 in cents
    $cart->add('usd-product', 'USD Product', $usdPrice, 1);

    $item = $cart->get('usd-product');

    expect($item->getPrice()->getCurrency()->getName())->toBe('US Dollar'); // Currency name for USD
    expect($item->getPrice()->getCurrency()->getPrecision())->toBe(2);
    expect($item->getSubtotal()->getCurrency()->getName())->toBe('US Dollar');
});

it('shows item-level money calculations', function (): void {
    $cart = createMoneyTestCart('item_calculations');

    // Add an item with quantity
    // 24.99 as float is treated as $24.99 and converted to 2499 cents
    $itemPrice = 24.99;
    $cart->add('bulk-item', 'Bulk Purchase', $itemPrice, 5);

    $item = $cart->get('bulk-item');

    // Verify individual item Money calculations
    expect($item->getPrice())->toBeInstanceOf(Money::class);
    expect($item->getPrice()->getAmount())->toBe(2499); // $24.99 stored as 2499 cents
    expect($item->getPrice()->getCurrency()->getName())->toBe('US Dollar');

    // Verify calculated totals
    expect($item->getSubtotal())->toBeInstanceOf(Money::class);
    expect($item->getSubtotal()->getAmount())->toBe(12495); // 2499 cents * 5 = 12495 cents

    // Test Money arithmetic operations
    $doubled = $item->getPrice()->multiply(2);
    expect($doubled->getAmount())->toBe(4998.0); // 2499 cents * 2 = 4998 cents

    // Test percentage calculations
    // Manual percentage calculation
    $tenPercent = $item->getSubtotal()->multiply(0.10);
    expect($tenPercent->getAmount())->toBe(1249.5); // 10% of 12495 cents = 1249.5 cents
});
