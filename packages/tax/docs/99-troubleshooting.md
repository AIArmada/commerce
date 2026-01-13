---
title: Troubleshooting
---

# Troubleshooting

## Common Issues

### No Tax Applied

**Symptom:** `TaxResultData::taxAmount` returns 0 when you expect a tax.

**Checks:**

1. **Zone exists and is active**
   ```php
   TaxZone::where('is_active', true)->get();
   ```

2. **Rate exists for zone + tax class**
   ```php
   TaxRate::where('zone_id', $zoneId)
       ->where('tax_class', 'standard')
       ->where('is_active', true)
       ->get();
   ```

3. **Zone resolution finds address**
   ```php
   // Check your context address matches zone criteria
   $zone = TaxZone::where('countries', 'like', '%"MY"%')
       ->where('is_active', true)
       ->first();
   ```

4. **Customer has exemption**
   ```php
   TaxExemption::where('exemptable_id', $customerId)
       ->approved()
       ->active()
       ->get();
   ```

### Wrong Tax Amount

**Symptom:** Tax calculation differs from expected.

**Checks:**

1. **Price inclusion setting**
   ```php
   // If prices_include_tax is true, tax is extracted from amount
   // RM 106 with 6% tax → taxAmount = 600, net = 10000
   config('tax.defaults.prices_include_tax');
   ```

2. **Compound tax stacking**
   ```php
   // Check rate priorities and is_compound flags
   TaxRate::where('zone_id', $zoneId)
       ->orderBy('priority', 'desc')
       ->get(['name', 'rate', 'priority', 'is_compound']);
   ```

3. **Rounding mode**
   ```php
   // round_at_subtotal: false = round per line item
   // round_at_subtotal: true = round after summing
   config('tax.defaults.round_at_subtotal');
   ```

### Zone Not Found

**Symptom:** No zone matches the provided address.

**Checks:**

1. **Country code format** - Use ISO 2-letter codes (MY, SG, US)
2. **Postcode format** - Some zones use wildcards (43* matches 43000-43999)
3. **Zone priority** - More specific zones should have higher priority

```php
// Debug zone resolution
TaxZone::where('is_active', true)
    ->orderBy('priority', 'desc')
    ->get(['name', 'countries', 'states', 'postcodes', 'priority']);
```

### Owner Scoping Issues

**Symptom:** Records not visible or cross-tenant data leaking.

**Checks:**

1. **Owner resolver bound**
   ```php
   app(\AIArmada\Support\Contracts\OwnerResolverInterface::class);
   ```

2. **Owner mode enabled**
   ```php
   config('tax.features.owner.enabled');
   ```

3. **Include global setting**
   ```php
   // false = owner-only, true = owner + global (owner_id = null)
   config('tax.features.owner.include_global');
   ```

## Debugging

### Log Tax Calculations

```php
use AIArmada\Tax\Facades\Tax;
use Illuminate\Support\Facades\Log;

$result = Tax::calculateTax(10000, 'standard', null, [
    'shipping_address' => $address,
]);

Log::debug('Tax calculation', [
    'input' => [
        'amount' => 10000,
        'class' => 'standard',
        'address' => $address,
    ],
    'output' => [
        'tax' => $result->taxAmount,
        'rate' => $result->ratePercentage,
        'zone' => $result->zoneName,
        'breakdown' => $result->breakdown,
    ],
]);
```

### Test Zone Matching

```php
use AIArmada\Tax\Models\TaxZone;

// Find which zone matches an address
$zones = TaxZone::where('is_active', true)
    ->orderBy('priority', 'desc')
    ->get();

foreach ($zones as $zone) {
    // Check country
    $countries = json_decode($zone->countries, true) ?? [];
    if (!in_array('MY', $countries)) continue;
    
    // Check postcode
    $postcodes = json_decode($zone->postcodes, true) ?? [];
    // ... matching logic
    
    dump("Zone {$zone->name} matches");
}
```

## Migration Issues

### JSON Column Type

If you encounter JSON column errors:

```php
// config/tax.php
'database' => [
    'json_column_type' => 'json', // or 'text' for older MySQL
],
```

### UUID Column Type

Ensure your database supports UUIDs. For MySQL < 8.0, UUIDs are stored as CHAR(36).

## Performance

### Slow Zone Resolution

1. Add database indexes on zone matching columns
2. Cache resolved zones per customer session
3. Consider denormalizing zone data for high-traffic sites

### Many Tax Rates

If zones have many rates:

```php
// Eager load rates when fetching zones
TaxZone::with('rates')->find($zoneId);
```

## Getting Help

1. Check the [configuration reference](03-configuration.md)
2. Review [usage examples](04-usage.md)
3. Enable debug logging for tax calculations
4. Open an issue with reproduction steps
