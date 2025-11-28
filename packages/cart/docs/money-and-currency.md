# Money & Currency Handling

Precise financial calculations using `Akaunting\Money\Money` objects.

## Why Money Objects?

```php
// ❌ Floating-point arithmetic
$price = 0.1 + 0.2; // 0.30000000000000004

// ✅ Money objects
use Akaunting\Money\Money;
$price = Money::MYR(1000)->add(Money::MYR(2000)); // MYR 30.00
```

## Creating Money Objects

```php
use Akaunting\Money\Money;
use Akaunting\Money\Currency;

// Static constructor (recommended)
$price = Money::MYR(1999); // MYR 19.99 (cents)

// Explicit constructor
$price = new Money(1999, new Currency('MYR'));

// Multiple currencies
Money::USD(5000);  // $50.00
Money::EUR(4200);  // €42.00
Money::MYR(1999);  // RM19.99
```

## Cart Integration

```php
// Add with Money object
Cart::add('sku-123', 'Product', Money::MYR(8999), 2);

// Or string (auto-converted)
Cart::add('sku-456', 'Product', '29.99', 1);

// Retrieve as Money
$item = Cart::get('sku-123');
$price = $item->getPrice(); // Money instance
echo $price->format();      // "RM89.99"
```

## Retrieving Totals

```php
$total = Cart::total();

// Display
echo $total->format();     // "RM199.99"

// Raw amount (cents)
$cents = $total->getAmount(); // 19999

// Float (for calculations only)
$float = $total->getValue(); // 199.99
```

## Price Sanitization

String prices are automatically cleaned:

```php
Cart::add('item-1', 'Product', '99.99', 1);      // → 9999 cents
Cart::add('item-2', 'Product', 'RM 99.99', 1);   // → 9999 cents
Cart::add('item-3', 'Product', '1,234.56', 1);   // → 123456 cents
```

## Configuration

```php
// config/cart.php
'money' => [
    'currency' => env('CART_CURRENCY', 'MYR'),
    'locale' => env('CART_LOCALE', 'en_MY'),
],
```

## Formatting

```php
$money = Money::MYR(123456); // MYR 1234.56

echo $money->format();        // "RM1,234.56"
echo $money->formatSimple();  // "1234.56"
echo $money->getValue();      // 1234.56 (float)
echo $money->getAmount();     // 123456 (cents)
```

## Arithmetic

```php
$price = Money::MYR(10000); // MYR 100.00

$price->add(Money::MYR(2000));      // MYR 120.00
$price->subtract(Money::MYR(1500)); // MYR 85.00
$price->multiply(2);                // MYR 200.00
$price->multiply(1.06);             // MYR 106.00 (6% tax)
$price->divide(2);                  // MYR 50.00
```

## Comparisons

```php
$price1 = Money::MYR(9999);
$price2 = Money::MYR(8999);

$price1->greaterThan($price2);  // true
$price1->equals($price2);       // false
$price1->lessThan($price2);     // false
$price1->isZero();              // false
$price1->isPositive();          // true
```

## Multi-Currency

### Single Currency per Cart (Recommended)

```php
Cart::setMetadata('currency', 'MYR');

// Validate on add
if ($request->currency !== Cart::getMetadata('currency')) {
    throw new \Exception('Cannot mix currencies');
}
```

### Per-Instance Currency

```php
Cart::instance('usd-cart')->setMetadata('currency', 'USD');
Cart::instance('eur-cart')->setMetadata('currency', 'EUR');
```

## API Responses

```php
return response()->json([
    'total' => [
        'amount' => Cart::total()->getAmount(),
        'formatted' => Cart::total()->format(),
        'currency' => 'MYR',
    ],
]);
```

## Common Pitfalls

### ❌ Storing as Float

```php
// Wrong
$table->float('total');

// Correct (store cents)
$table->unsignedBigInteger('total');
$table->string('currency', 3);
```

### ❌ Using getValue() for Storage

```php
// Wrong
$order->total = Cart::total()->getValue();

// Correct
$order->total = Cart::total()->getAmount();
```

### ❌ Currency Mismatch

```php
// This throws exception
$myr = Money::MYR(10000);
$usd = Money::USD(10000);
$total = $myr->add($usd); // Error!

// Convert first
$usdInMyr = $converter->convert($usd, 'MYR');
$total = $myr->add($usdInMyr);
```

## Testing

```php
it('calculates total correctly', function () {
    Cart::add('sku-1', 'Product A', Money::MYR(5000), 2);
    Cart::add('sku-2', 'Product B', Money::MYR(3000), 1);
    
    expect(Cart::total()->getAmount())->toBe(13000);
});
```

## Next Steps

- [Cart Operations](cart-operations.md) – Working with items
- [Conditions](conditions.md) – Pricing conditions
- [API Reference](api-reference.md) – Complete methods
