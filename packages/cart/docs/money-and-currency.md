# 💰 Money & Currency Handling

> **Precise financial calculations with zero rounding errors—powered by akaunting/laravel-money for production-grade e-commerce.**

AIArmada Cart uses the **akaunting/laravel-money** package to ensure accurate monetary calculations. All cart totals, subtotals, item prices, and condition amounts are represented as `Money` objects, eliminating floating-point precision issues.

## 📋 Table of Contents

- [Why Money Objects?](#-why-money-objects)
- [Basic Usage](#-basic-usage)
- [Price Input & Sanitization](#-price-input--sanitization)
- [Currency Configuration](#-currency-configuration)
- [Formatting & Display](#-formatting--display)
- [Multi-Currency Strategies](#-multi-currency-strategies)
- [Common Patterns](#-common-patterns)
- [Calculations & Precision](#-calculations--precision)
- [API Responses](#-api-responses)
- [Testing with Money](#-testing-with-money)
- [Common Pitfalls](#-common-pitfalls)

---

## 🤔 Why Money Objects?

### The Floating-Point Problem

```php
// ❌ WRONG: Floating-point arithmetic
$price = 0.1 + 0.2; // 0.30000000000000004
$total = 10.95 * 3; // 32.849999999999994

// ✅ CORRECT: Money objects
use Akaunting\Money\Money;

$price = Money::MYR(1000)->add(Money::MYR(2000)); // MYR 30.00
$total = Money::MYR(1095)->multiply(3); // MYR 32.85
```

### Benefits of Money Objects

- ✅ **Precision**: No floating-point errors in financial calculations
- ✅ **Currency-Aware**: Automatically handles currency symbols and formatting
- ✅ **Immutable**: Operations return new instances, preventing mutation bugs
- ✅ **Type Safety**: Compiler catches currency mismatches
- ✅ **Comparison**: Safe equality and comparison operators
- ✅ **Serialization**: JSON-friendly for APIs

---

## 🚀 Basic Usage

### Creating Money Objects

```php
use Akaunting\Money\Money;
use Akaunting\Money\Currency;

// Method 1: Static constructor (recommended)
$price = Money::MYR(1999); // MYR 19.99 (cents notation)

// Method 2: Explicit constructor
$price = new Money(1999, new Currency('MYR'));

// Method 3: From string (uses sanitization)
$price = Cart::sanitizePrice('19.99'); // Returns Money object

// Method 4: Multiple currencies
$usd = Money::USD(5000); // USD 50.00
$eur = Money::EUR(4200); // EUR 42.00
$myr = Money::MYR(1999); // MYR 19.99
```

### Working with Cart Items

```php
// Adding items with Money objects
Cart::add([
    'id' => 'sku-123',
    'name' => 'Premium Shirt',
    'price' => Money::MYR(8999), // MYR 89.99
    'quantity' => 2,
]);

// Or let the cart sanitize strings automatically
Cart::add([
    'id' => 'sku-456',
    'name' => 'Basic Tee',
    'price' => '29.99', // Converted to Money::MYR(2999)
    'quantity' => 1,
]);

// Retrieve as Money objects
$item = Cart::get('sku-123');
$itemPrice = $item->getPrice(); // Money instance
echo $itemPrice->format(); // "RM89.99"
```

### Retrieving Totals

```php
// All totals return Money objects
$subtotal = Cart::subtotal(); // Money::MYR(...)
$total = Cart::total();       // Money::MYR(...)
$savings = Cart::savings();   // Money::MYR(...)

// Display formatted
echo $total->format();        // "RM199.99"
echo $total->formatSimple();  // "199.99"

// Get raw amount (in cents)
$cents = $total->getAmount(); // 19999

// Convert to float (for calculations only, not storage!)
$float = $total->getValue();  // 199.99
```

---

## 🔧 Price Input & Sanitization

The cart automatically sanitizes price inputs to ensure consistency.

### Supported Input Formats

```php
// All of these are valid inputs
Cart::add('sku-1', 'Product', '99.99',    1); // String with decimal
Cart::add('sku-2', 'Product', 9999,       1); // Integer (cents)
Cart::add('sku-3', 'Product', 99.99,      1); // Float (converted safely)
Cart::add('sku-4', 'Product', 'RM 99.99', 1); // With currency symbol
Cart::add('sku-5', 'Product', '$99.99',   1); // With $ symbol
Cart::add('sku-6', 'Product', '99,99',    1); // European format
Cart::add('sku-7', 'Product', Money::MYR(9999), 1); // Money object

// Invalid inputs throw InvalidCartItemException
Cart::add('sku-8', 'Product', 'invalid', 1); // ❌ Exception
Cart::add('sku-9', 'Product', '',        1); // ❌ Exception
Cart::add('sku-10', 'Product', null,     1); // ❌ Exception
```

### Manual Sanitization

```php
use AIArmada\Cart\Cart;

// Sanitize price strings
$money = Cart::sanitizePrice('99.99');        // Money::MYR(9999)
$money = Cart::sanitizePrice('RM 1,234.56'); // Money::MYR(123456)
$money = Cart::sanitizePrice(Money::MYR(5000)); // Passes through unchanged

// Custom currency (if needed)
$money = Cart::sanitizePrice('99.99', 'USD'); // Money::USD(9999)
```

### Sanitization Rules

1. **Currency symbols** are stripped: `RM`, `$`, `€`, etc.
2. **Thousands separators** are removed: `1,234.56` → `1234.56`
3. **Whitespace** is trimmed
4. **Decimal places** are converted to cents: `99.99` → `9999`
5. **Empty strings** throw exceptions
6. **Non-numeric** values throw exceptions

---

## ⚙️ Currency Configuration

### Default Currency

```php
// config/cart.php
return [
    'money' => [
        'default_currency' => 'MYR', // Malaysian Ringgit
    ],
];
```

### Supported Currencies

The package supports **all ISO 4217 currencies**:

```php
Money::MYR(1000); // Malaysian Ringgit
Money::USD(1000); // US Dollar
Money::EUR(1000); // Euro
Money::GBP(1000); // British Pound
Money::SGD(1000); // Singapore Dollar
Money::THB(1000); // Thai Baht
Money::IDR(1000); // Indonesian Rupiah
Money::JPY(1000); // Japanese Yen (no decimals)
Money::CNY(1000); // Chinese Yuan
Money::AUD(1000); // Australian Dollar
// ... and 150+ more
```

### Currency Properties

```php
$currency = new Currency('MYR');

$currency->getCurrency();      // "MYR"
$currency->getName();          // "Malaysian Ringgit"
$currency->getSymbol();        // "RM"
$currency->getPrecision();     // 2
$currency->getSubunit();       // 100
$currency->getThousandsSeparator(); // ","
$currency->getDecimalMark();   // "."
```

---

## 🎨 Formatting & Display

### Basic Formatting

```php
$money = Money::MYR(123456); // MYR 1234.56

// Full format with symbol
echo $money->format();           // "RM1,234.56"

// Simple format (number only)
echo $money->formatSimple();     // "1234.56"

// For APIs/JSON
echo $money->getValue();         // 1234.56 (float)
echo $money->getAmount();        // 123456 (cents)
echo $money->getCurrency()->getCurrency(); // "MYR"
```

### Custom Formatting

```php
use Akaunting\Money\Money;

$money = Money::MYR(999999); // MYR 9999.99

// Custom locale formatting
echo $money->format('en_MY');    // "RM9,999.99"
echo $money->format('ms_MY');    // "RM9,999.99"

// Without grouping
echo $money->formatWithoutZeroes(); // "RM9999.99"

// Currency code instead of symbol
$formatted = sprintf(
    '%s %s',
    $money->getCurrency()->getCurrency(),
    $money->formatSimple()
); // "MYR 9999.99"
```

### Blade Templates

```blade
{{-- Cart totals --}}
<div class="cart-summary">
    <p>Subtotal: {{ Cart::subtotal()->format() }}</p>
    <p>Tax: {{ Cart::getCondition('tax')->getCalculatedValue()->format() }}</p>
    <p>Total: <strong>{{ Cart::total()->format() }}</strong></p>
</div>

{{-- Item prices --}}
@foreach(Cart::getItems() as $item)
    <tr>
        <td>{{ $item->name }}</td>
        <td>{{ $item->getPrice()->format() }}</td>
        <td>{{ $item->quantity }}</td>
        <td>{{ $item->getSubtotal()->format() }}</td>
    </tr>
@endforeach
```

### API Responses

```php
// Controller
return response()->json([
    'cart' => [
        'items' => Cart::getItems()->map(fn($item) => [
            'id' => $item->id,
            'name' => $item->name,
            'price' => [
                'amount' => $item->getPrice()->getAmount(),
                'formatted' => $item->getPrice()->format(),
                'currency' => 'MYR',
            ],
            'quantity' => $item->quantity,
            'subtotal' => [
                'amount' => $item->getSubtotal()->getAmount(),
                'formatted' => $item->getSubtotal()->format(),
                'currency' => 'MYR',
            ],
        ]),
        'totals' => [
            'subtotal' => [
                'amount' => Cart::subtotal()->getAmount(),
                'formatted' => Cart::subtotal()->format(),
            ],
            'total' => [
                'amount' => Cart::total()->getAmount(),
                'formatted' => Cart::total()->format(),
            ],
        ],
    ],
]);
```

---

## 🌍 Multi-Currency Strategies

### Strategy 1: Single Currency per Cart (Recommended)

The simplest approach: enforce one currency per cart instance.

```php
// Store currency in cart metadata
Cart::setMetadata('currency', 'MYR');

// Validate on item add
public function addToCart(Request $request): void
{
    $cartCurrency = Cart::getMetadata('currency', config('cart.money.default_currency'));
    
    if ($request->currency !== $cartCurrency) {
        throw new \Exception('Cannot mix currencies in cart');
    }
    
    Cart::add([
        'id' => $request->sku,
        'name' => $request->name,
        'price' => Money::{$cartCurrency}($request->price),
        'quantity' => $request->quantity,
    ]);
}
```

### Strategy 2: Per-Instance Currency

Use different cart instances for different currencies.

```php
// USD cart
Cart::setInstance('usd-cart');
Cart::setMetadata('currency', 'USD');
Cart::add('sku-1', 'Product', Money::USD(5000), 1);

// EUR cart
Cart::setInstance('eur-cart');
Cart::setMetadata('currency', 'EUR');
Cart::add('sku-2', 'Product', Money::EUR(4200), 1);

// Retrieve by instance
Cart::setInstance('usd-cart');
$usdTotal = Cart::total(); // Money::USD(...)
```

### Strategy 3: Currency Conversion (Advanced)

Convert currencies at checkout using exchange rates.

```php
use Akaunting\Money\Currency;

class CurrencyConverter
{
    public function convert(Money $money, string $toCurrency): Money
    {
        $rate = $this->getExchangeRate(
            $money->getCurrency()->getCurrency(),
            $toCurrency
        );
        
        $convertedAmount = (int) round($money->getAmount() * $rate);
        
        return new Money($convertedAmount, new Currency($toCurrency));
    }
    
    private function getExchangeRate(string $from, string $to): float
    {
        // Fetch from API or database
        return Cache::remember("exchange_{$from}_{$to}", 3600, function () use ($from, $to) {
            return Http::get("https://api.exchangerate.com/latest?base={$from}")
                ->json("rates.{$to}");
        });
    }
}

// Usage
$converter = app(CurrencyConverter::class);

Cart::setInstance('myr-cart');
Cart::add('sku-1', 'Product', Money::MYR(10000), 1);

$total = Cart::total(); // Money::MYR(10000)
$usdTotal = $converter->convert($total, 'USD'); // Money::USD(2150) approx
```

### Strategy 4: Multi-Currency Items (Complex)

Store prices in multiple currencies and display based on user preference.

```php
// Product model with multi-currency prices
class Product extends Model
{
    protected $casts = [
        'prices' => 'array', // ['MYR' => 10000, 'USD' => 2150, 'EUR' => 1950]
    ];
    
    public function getPriceInCurrency(string $currency): Money
    {
        $amount = $this->prices[$currency] ?? throw new \Exception("Price not available in {$currency}");
        
        return new Money($amount, new Currency($currency));
    }
}

// Controller
$product = Product::find($request->product_id);
$userCurrency = auth()->user()->preferred_currency ?? 'MYR';

Cart::add([
    'id' => $product->id,
    'name' => $product->name,
    'price' => $product->getPriceInCurrency($userCurrency),
    'quantity' => $request->quantity,
    'attributes' => [
        'available_currencies' => array_keys($product->prices),
    ],
]);
```

---

## 🎯 Common Patterns

### Pattern 1: Dynamic Pricing

```php
// Calculate price based on quantity (bulk discount)
$basePrice = Money::MYR(5000); // MYR 50.00
$quantity = 10;

if ($quantity >= 10) {
    $price = $basePrice->multiply(0.9); // 10% bulk discount
} elseif ($quantity >= 5) {
    $price = $basePrice->multiply(0.95); // 5% discount
} else {
    $price = $basePrice;
}

Cart::add('sku-1', 'Bulk Product', $price, $quantity);
```

### Pattern 2: Price Comparison

```php
$price1 = Money::MYR(9999);
$price2 = Money::MYR(8999);

if ($price1->greaterThan($price2)) {
    echo "Price 1 is more expensive";
}

if ($price1->equals($price2)) {
    echo "Prices are equal";
}

if ($price1->lessThan($price2)) {
    echo "Price 1 is cheaper";
}

// Get the higher/lower price
$higher = $price1->greaterThan($price2) ? $price1 : $price2;
$lower = $price1->lessThan($price2) ? $price1 : $price2;
```

### Pattern 3: Price Aggregation

```php
// Sum all item prices
$total = Cart::getItems()->reduce(
    fn($carry, $item) => $carry->add($item->getSubtotal()),
    Money::MYR(0)
);

// Average price per item
$items = Cart::getItems();
$totalPrice = $items->reduce(
    fn($carry, $item) => $carry->add($item->getPrice()),
    Money::MYR(0)
);
$avgPrice = $totalPrice->divide($items->count());
```

### Pattern 4: Conditional Pricing

```php
// Member discount
$regularPrice = Money::MYR(10000);
$memberPrice = auth()->user()->is_member 
    ? $regularPrice->multiply(0.8) 
    : $regularPrice;

Cart::add('sku-1', 'Product', $memberPrice, 1);
```

---

## 🔢 Calculations & Precision

### Arithmetic Operations

```php
$price = Money::MYR(10000); // MYR 100.00

// Addition
$newPrice = $price->add(Money::MYR(2000)); // MYR 120.00

// Subtraction
$discounted = $price->subtract(Money::MYR(1500)); // MYR 85.00

// Multiplication
$doubled = $price->multiply(2); // MYR 200.00
$taxed = $price->multiply(1.06); // MYR 106.00 (6% tax)

// Division
$half = $price->divide(2); // MYR 50.00
$split = $price->divide(3); // MYR 33.33 (rounded)
```

### Percentage Calculations

```php
$price = Money::MYR(10000); // MYR 100.00

// 10% discount
$discount = $price->multiply(0.10); // MYR 10.00
$finalPrice = $price->subtract($discount); // MYR 90.00

// Or use conditions (recommended)
Cart::add('sku-1', 'Product', $price, 1);
Cart::addDiscount('10% OFF', '-10%');
$total = Cart::total(); // Automatically calculated
```

### Rounding

```php
// Money objects automatically round to currency precision
$amount = Money::MYR(10049); // MYR 100.49
$divided = $amount->divide(3); // MYR 33.50 (rounds 33.496666...)

// Japanese Yen (no decimals)
$yen = Money::JPY(10000); // ¥10,000 (no cents)
$split = $yen->divide(3); // ¥3,333 (rounds 3333.33...)
```

### Comparison & Zero Checks

```php
$price = Money::MYR(0);

// Check if zero
if ($price->isZero()) {
    echo "Free item!";
}

// Check if positive
if ($price->isPositive()) {
    echo "Has value";
}

// Check if negative
if ($price->isNegative()) {
    echo "Refund/credit";
}
```

---

## 📡 API Responses

### Best Practice Format

```php
// Controller
public function getCart(): JsonResponse
{
    return response()->json([
        'success' => true,
        'data' => [
            'items' => Cart::getItems()->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'price' => [
                        'amount' => $item->getPrice()->getAmount(), // 9999 (cents)
                        'formatted' => $item->getPrice()->format(), // "RM99.99"
                        'value' => $item->getPrice()->getValue(),   // 99.99 (float)
                        'currency' => 'MYR',
                    ],
                    'subtotal' => [
                        'amount' => $item->getSubtotal()->getAmount(),
                        'formatted' => $item->getSubtotal()->format(),
                        'currency' => 'MYR',
                    ],
                ];
            }),
            'summary' => [
                'subtotal' => [
                    'amount' => Cart::subtotal()->getAmount(),
                    'formatted' => Cart::subtotal()->format(),
                    'currency' => 'MYR',
                ],
                'total' => [
                    'amount' => Cart::total()->getAmount(),
                    'formatted' => Cart::total()->format(),
                    'currency' => 'MYR',
                ],
                'item_count' => Cart::countItems(),
            ],
        ],
    ]);
}
```

### Frontend Consumption

```javascript
// JavaScript/React example
fetch('/api/cart')
    .then(res => res.json())
    .then(data => {
        // Use formatted for display
        document.getElementById('total').textContent = data.data.summary.total.formatted;
        
        // Use amount for calculations
        const totalCents = data.data.summary.total.amount;
        
        // Use currency for context
        console.log(`Currency: ${data.data.summary.total.currency}`);
    });
```

---

## 🧪 Testing with Money

### PHPUnit/Pest Assertions

```php
use Akaunting\Money\Money;

it('calculates cart total correctly', function () {
    Cart::add('sku-1', 'Product A', Money::MYR(5000), 2);
    Cart::add('sku-2', 'Product B', Money::MYR(3000), 1);
    
    $total = Cart::total();
    
    expect($total)->toBeInstanceOf(Money::class);
    expect($total->getAmount())->toBe(13000);
    expect($total->format())->toBe('RM130.00');
});

it('applies percentage discount correctly', function () {
    Cart::add('sku-1', 'Product', Money::MYR(10000), 1);
    Cart::addDiscount('10% OFF', '-10%');
    
    $total = Cart::total();
    
    expect($total->getAmount())->toBe(9000); // MYR 90.00
});
```

### Factory Patterns

```php
// tests/Factories/MoneyFactory.php
class MoneyFactory
{
    public static function myr(int $cents): Money
    {
        return Money::MYR($cents);
    }
    
    public static function random(int $min = 1000, int $max = 100000): Money
    {
        return Money::MYR(fake()->numberBetween($min, $max));
    }
}

// Usage in tests
it('handles random prices', function () {
    Cart::add('sku-1', 'Product', MoneyFactory::random(), 1);
    
    expect(Cart::total()->getAmount())->toBeGreaterThan(0);
});
```

---

## ⚠️ Common Pitfalls

### Pitfall 1: Storing as Float

```php
// ❌ WRONG: Storing price as float in database
Schema::create('orders', function (Blueprint $table) {
    $table->float('total'); // Precision issues!
});

// ✅ CORRECT: Store as integer (cents)
Schema::create('orders', function (Blueprint $table) {
    $table->unsignedBigInteger('total'); // Cents
    $table->string('currency', 3)->default('MYR');
});

// Save to database
$order->total = Cart::total()->getAmount(); // Integer cents
$order->currency = 'MYR';
$order->save();

// Retrieve from database
$total = new Money($order->total, new Currency($order->currency));
```

### Pitfall 2: Comparing with ==

```php
$price1 = Money::MYR(9999);
$price2 = Money::MYR(9999);

// ❌ WRONG: Object comparison
if ($price1 == $price2) {} // May not work as expected

// ✅ CORRECT: Use equals() method
if ($price1->equals($price2)) {} // Reliable
```

### Pitfall 3: JSON Serialization

```php
// ❌ WRONG: Direct JSON encoding loses type
$json = json_encode(['price' => Money::MYR(9999)]);
// Result: {"price":{...}} - complex object

// ✅ CORRECT: Extract amount before encoding
$json = json_encode([
    'price' => [
        'amount' => Money::MYR(9999)->getAmount(),
        'currency' => 'MYR',
        'formatted' => Money::MYR(9999)->format(),
    ],
]);
```

### Pitfall 4: Currency Mismatch

```php
// ❌ WRONG: Mixing currencies
$myr = Money::MYR(10000);
$usd = Money::USD(10000);
$total = $myr->add($usd); // Exception: Currency mismatch!

// ✅ CORRECT: Convert first or enforce same currency
$usdConverted = $converter->convert($usd, 'MYR');
$total = $myr->add($usdConverted);
```

### Pitfall 5: Using getValue() for Storage

```php
// ❌ WRONG: Storing float value
$order->total = Cart::total()->getValue(); // 199.99 (float) - NEVER DO THIS!

// ✅ CORRECT: Store integer amount
$order->total = Cart::total()->getAmount(); // 19999 (cents)
```

### Pitfall 6: Forgetting Currency in Conditions

```php
// ❌ WRONG: String amounts in conditions
Cart::addCondition(new CartCondition(
    'shipping',
    'fee',
    'subtotal',
    '15.00' // Wrong! This becomes cents internally
));

// ✅ CORRECT: Use Money objects
Cart::addCondition(new CartCondition(
    'shipping',
    'fee',
    'subtotal',
    Money::MYR(1500) // MYR 15.00
));
```

---

## 📚 Related Documentation

- **[Cart Operations](cart-operations.md)** – Working with items and totals
- **[Conditions](conditions.md)** – Using Money in pricing conditions
- **[Configuration](configuration.md)** – Currency configuration options
- **[API Reference](api-reference.md)** – Money-related methods
- **[Testing](testing.md)** – Testing strategies with Money objects

---

## 🔗 External Resources

- **[akaunting/laravel-money Documentation](https://github.com/akaunting/laravel-money)** – Official package docs
- **[Money PHP](https://github.com/moneyphp/money)** – Underlying library
- **[ISO 4217 Currency Codes](https://en.wikipedia.org/wiki/ISO_4217)** – Complete currency list

---

**Next Steps:**
- [Configure your default currency](configuration.md)
- [Apply pricing conditions](conditions.md)
- [Format API responses](api-reference.md)
