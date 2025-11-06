# Testing Guide

## Quick Start

```bash
# Run all tests
composer test

# Run with coverage
composer test-coverage

# Run specific package
vendor/bin/pest packages/cart/tests
```

## Overview

We use **Pest v4** for all testing. Target: >80% code coverage.

### Test Types

- **Unit Tests** (`tests/Unit/`) - Test individual classes in isolation
- **Feature Tests** (`tests/Feature/`) - Test component integration
- **Browser Tests** (`tests/Browser/`) - UI testing for Filament packages

---

## Running Tests

### All Tests

```bash
composer test
```

### With Coverage

```bash
composer test-coverage
```

### Specific Package

```bash
vendor/bin/pest packages/cart/tests
vendor/bin/pest packages/chip/tests
```

### Specific Test File

### Filter by Test Name

```bash
vendor/bin/pest --filter=CartCondition
```

### Parallel Execution

```bash
vendor/bin/pest --parallel
```

## Writing Tests

### Basic Structure

```php
it('adds items to cart', function () {
    $cart = Cart::create();
    
    $cart->addItem([
        'id' => 1,
        'name' => 'Test Product',
        'price' => 100,
        'quantity' => 2,
    ]);
    
    expect($cart->items)->toHaveCount(1);
    expect($cart->subtotal())->toBe(200.00);
});
```

### Using beforeEach/afterEach

```php
beforeEach(function () {
    $this->cart = Cart::create();
});

it('calculates subtotal', function () {
    $this->cart->addItem(['id' => 1, 'price' => 50, 'quantity' => 2]);
    expect($this->cart->subtotal())->toBe(100.00);
});
```

### Datasets

```php
it('validates price formats', function (string $price, bool $valid) {
    expect(validatePrice($price))->toBe($valid);
})->with([
    ['10.99', true],
    ['invalid', false],
    ['-5.00', false],
]);
```

## Directory Structure

```
tests/
├── Unit/           # Individual classes/methods
├── Feature/        # Integration testing
└── Browser/        # UI testing (Filament)
```

For more detailed testing patterns, see the [Pest documentation](https://pestphp.com).

- Test files: `*Test.php`
- Test names: Descriptive sentences
  - Good: `it('calculates total with multiple conditions')`
  - Bad: `test_total()`

---

## Factories

Use factories to create test data:

```php
use AIArmada\Cart\Database\Factories\CartFactory;

// Create single cart
$cart = Cart::factory()->create();

// Create with relationships
$cart = Cart::factory()
    ->hasItems(3)
    ->hasConditions(2)
    ->create();

// Create multiple
$carts = Cart::factory()->count(10)->create();

// Create with attributes
$cart = Cart::factory()->create([
    'identifier' => 'test-cart',
    'instance' => 'wishlist',
]);
```

---

## Testing Patterns

### Testing Conditions

```php
it('applies percentage discount', function () {
    $cart = Cart::create();
    
    $cart->addItem([
        'id' => 1,
        'price' => 100,
        'quantity' => 1,
    ]);
    
    $cart->addCondition([
        'name' => 'SALE',
        'type' => 'discount',
        'value' => '-10%',
    ]);
    
    expect($cart->total())->toBe(90.00);
});
```

### Testing Events

```php
use Illuminate\Support\Facades\Event;

it('dispatches item added event', function () {
    Event::fake();
    
    $cart = Cart::create();
    $cart->addItem(['id' => 1, 'price' => 50]);
    
    Event::assertDispatched(CartItemAdded::class);
});
```

### Testing Exceptions

```php
it('throws exception for invalid item', function () {
    $cart = Cart::create();
    
    $cart->addItem(['invalid' => 'data']);
})->throws(InvalidCartItemException::class);
```

### Testing Database Transactions

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('saves cart to database', function () {
    $cart = Cart::create();
    
    $this->assertDatabaseHas('carts', [
        'identifier' => $cart->identifier,
    ]);
});
```

---

## Coverage

### Generate Coverage Report

```bash
vendor/bin/pest --coverage
```

### Minimum Coverage

We enforce minimum 80% coverage:

```bash
vendor/bin/pest --coverage --min=80
```

### Coverage HTML Report

```bash
vendor/bin/pest --coverage --coverage-html=coverage-report
```

Then open `coverage-report/index.html` in your browser.

### CI Coverage

Coverage is automatically tracked in GitHub Actions:
- See `.github/workflows/test-coverage.yml`
- Reports uploaded to Codecov
- PR comments show coverage changes

---

## Best Practices

### DO ✅

- Write tests before fixing bugs
- Test edge cases and error conditions
- Use descriptive test names
- Keep tests focused and small
- Use factories for test data
- Mock external dependencies
- Test public API, not implementation details

### DON'T ❌

- Test framework code (Laravel, Filament)
- Write tests that depend on each other
- Use sleep() or time delays
- Test private methods directly
- Commit commented-out tests
- Skip tests without good reason

---

## Debugging Tests

### Run Single Test

```bash
vendor/bin/pest --filter="adds items to cart"
```

### Enable Debug Output

```php
it('debugs cart state', function () {
    $cart = Cart::create();
    $cart->addItem(['id' => 1, 'price' => 50]);
    
    dump($cart->items); // Or dd() to stop execution
    
    expect($cart->items)->toHaveCount(1);
});
```

### Use PHPUnit Assertions

```php
it('checks database state', function () {
    $cart = Cart::create();
    
    $this->assertDatabaseHas('carts', ['id' => $cart->id]);
    $this->assertDatabaseCount('carts', 1);
});
```

---

## Resources

- [Pest Documentation](https://pestphp.com)
- [Laravel Testing](https://laravel.com/docs/testing)
- [PHPUnit Documentation](https://phpunit.de)

---

For questions about testing, see [CONTRIBUTING.md](../../CONTRIBUTING.md) or open a discussion.
