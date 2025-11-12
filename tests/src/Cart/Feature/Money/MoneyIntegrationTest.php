<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Storage\StorageInterface;
use Akaunting\Money\Money;

function createTestCart(string $instance = 'test'): Cart
{
    return new Cart(
        storage: new class implements StorageInterface
        {
            private array $data = [];

            public function has(string $identifier, string $instance): bool
            {
                return isset($this->data[$identifier][$instance]);
            }

            public function forget(string $identifier, string $instance): void
            {
                unset($this->data[$identifier][$instance]);
            }

            public function flush(): void
            {
                $this->data = [];
            }

            public function getInstances(string $identifier): array
            {
                return array_keys($this->data[$identifier] ?? []);
            }

            public function forgetIdentifier(string $identifier): void
            {
                unset($this->data[$identifier]);
            }

            public function getItems(string $identifier, string $instance): array
            {
                return $this->data[$identifier][$instance]['items'] ?? [];
            }

            public function putItems(string $identifier, string $instance, array $items): void
            {
                $this->data[$identifier][$instance]['items'] = $items;
            }

            public function getConditions(string $identifier, string $instance): array
            {
                return $this->data[$identifier][$instance]['conditions'] ?? [];
            }

            public function putConditions(string $identifier, string $instance, array $conditions): void
            {
                $this->data[$identifier][$instance]['conditions'] = $conditions;
            }

            public function putBoth(string $identifier, string $instance, array $items, array $conditions): void
            {
                $this->data[$identifier][$instance]['items'] = $items;
                $this->data[$identifier][$instance]['conditions'] = $conditions;
            }

            public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
            {
                $this->data[$identifier][$instance]['metadata'][$key] = $value;
            }

            public function putMetadataBatch(string $identifier, string $instance, array $metadata): void
            {
                $this->data[$identifier][$instance]['metadata'] = array_merge(
                    $this->data[$identifier][$instance]['metadata'] ?? [],
                    $metadata
                );
            }

            public function getMetadata(string $identifier, string $instance, string $key): mixed
            {
                return $this->data[$identifier][$instance]['metadata'][$key] ?? null;
            }

            public function clearMetadata(string $identifier, string $instance): void
            {
                $this->data[$identifier][$instance]['metadata'] = [];
            }

            public function clearAll(string $identifier, string $instance): void
            {
                $this->data[$identifier][$instance]['items'] = [];
                $this->data[$identifier][$instance]['conditions'] = [];
                $this->data[$identifier][$instance]['metadata'] = [];
            }

            public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
            {
                if (isset($this->data[$oldIdentifier][$instance])) {
                    $this->data[$newIdentifier][$instance] = $this->data[$oldIdentifier][$instance];
                    unset($this->data[$oldIdentifier][$instance]);

                    return true;
                }

                return false;
            }

            public function getVersion(string $identifier, string $instance): ?int
            {
                return null;
            }

            public function getId(string $identifier, string $instance): ?string
            {
                return null;
            }

            public function getAllMetadata(string $identifier, string $instance): array
            {
                return $this->data[$identifier][$instance]['metadata'] ?? [];
            }

            public function getCreatedAt(string $identifier, string $instance): ?string
            {
                return null;
            }

            public function getUpdatedAt(string $identifier, string $instance): ?string
            {
                return null;
            }
        },
        identifier: 'test-identifier',
        instanceName: $instance
    );
}

beforeEach(function (): void {
    config(['cart.money.default_currency' => 'USD']);
});

it('demonstrates money integration with cart package', function (): void {
    $cart = createTestCart('shopping');

    // Add items with precise Money prices
    $itemPrice = 1999.0; // 19.99 in cents
    $cart->add('product-1', 'Widget', $itemPrice, 2);

    $expensiveItemPrice = 29999.0; // 299.99 in cents
    $cart->add('product-2', 'Premium Widget', $expensiveItemPrice, 1);

    // Get items and verify Money objects
    $item1 = $cart->get('product-1');
    $item2 = $cart->get('product-2');

    expect($item1->getPrice()->getAmount())->toBe(1999.0); // 19.99 stored as 1999 cents
    expect($item1->getSubtotal()->getAmount())->toBe(3998.0); // 39.98 stored as 3998 cents

    expect($item2->getPrice()->getAmount())->toBe(29999.0); // 299.99 stored as 29999 cents
    expect($item2->getSubtotal()->getAmount())->toBe(29999.0); // 299.99 stored as 29999 cents

    // Test cart totals with Money precision
    expect($cart->count())->toBe(3); // Total quantity: 2 + 1 = 3
});

it('shows money precision advantages over float arithmetic', function (): void {
    $cart = createTestCart('precision_test');

    // Add items with prices that cause float precision issues
    $trickyPrice = 10.0; // 0.1 USD = 10 cents
    $cart->add('item-1', 'Tricky Item', $trickyPrice, 3);

    $item = $cart->get('item-1');

    // Money maintains precision: 0.1 * 3 = 0.3 exactly
    expect($item->getSubtotal()->getAmount())->toBe(30.0); // 0.3 stored as 30 cents

    // Compare with float calculation that would lose precision
    $floatResult = 0.1 * 3; // This can be 0.30000000000000004 in some cases
    expect($item->getSubtotal()->getAmount())->toBe(30.0); // Money is always exact (in cents)
});

it('handles complex cart scenarios with money precision', function (): void {
    $cart = createTestCart('complex_test');

    // Add items with different currencies - Cart uses default currency from config
    $expensiveItem = 129999.0; // 1299.99 in cents
    $budgetItem = 599.0; // 5.99 in cents
    $cart->add('expensive', 'Luxury Item', $expensiveItem, 1);
    $cart->add('budget', 'Budget Item', $budgetItem, 3);

    // Test precision calculations
    $expensiveCartItem = $cart->get('expensive');
    $budgetCartItem = $cart->get('budget');

    expect($expensiveCartItem->getPrice()->getAmount())->toBe(129999.0); // 1299.99 stored as 129999 cents
    expect($budgetCartItem->getPrice()->getAmount())->toBe(599.0); // 5.99 stored as 599 cents
    expect($budgetCartItem->getSubtotal()->getAmount())->toBe(1797.0); // 17.97 stored as 1797 cents

    // Total should be precise - Cart returns CartMoney, get amount
    $total = $cart->total()->getAmount();
    expect($total)->toBe(131796.0); // Cart total() returns cents
});

it('demonstrates money currency safety', function (): void {
    $cart = createTestCart('currency_test');

    // Add items with different currencies - should maintain currency integrity
    $usdPrice = 1999.0; // 19.99 in cents
    $cart->add('usd-product', 'USD Product', $usdPrice, 1);

    $item = $cart->get('usd-product');

    expect($item->getPrice()->getCurrency()->getName())->toBe('US Dollar'); // Currency name for USD
    expect($item->getPrice()->getCurrency()->getPrecision())->toBe(2);
    expect($item->getSubtotal()->getCurrency()->getName())->toBe('US Dollar');
});

it('shows item-level money calculations', function (): void {
    $cart = createTestCart('item_calculations');

    // Add an item with quantity
    $itemPrice = 2499.0; // 24.99 in cents
    $cart->add('bulk-item', 'Bulk Purchase', $itemPrice, 5);

    $item = $cart->get('bulk-item');

    // Verify individual item Money calculations
    expect($item->getPrice())->toBeInstanceOf(Money::class);
    expect($item->getPrice()->getAmount())->toBe(2499.0); // 24.99 stored as 2499 cents
    expect($item->getPrice()->getCurrency()->getName())->toBe('US Dollar'); // Currency name for USD

    // Verify calculated totals
    expect($item->getSubtotal())->toBeInstanceOf(Money::class);
    expect($item->getSubtotal()->getAmount())->toBe(12495.0); // 124.95 stored as 12495 cents

    // Test Money arithmetic operations
    $doubled = $item->getPrice()->multiply(2);
    expect($doubled->getAmount())->toBe(4998.0); // 49.98 stored as 4998 cents

    // Test percentage calculations
    // Manual percentage calculation
    $tenPercent = $item->getSubtotal()->multiply(0.10);
    expect($tenPercent->getAmount())->toBe(1249.5); // 10% of 12495 cents = 1249.5 cents
});
