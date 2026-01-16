---
title: Troubleshooting
---

# Troubleshooting

Common issues and solutions when working with the affiliates package.

## Attribution Issues

### Affiliate Code Not Being Tracked

**Symptoms:** Visitors with affiliate links aren't being attributed.

**Check:**

1. **Middleware is registered:**
```php
// Check if middleware is in the web group
php artisan route:list --name=web
```

2. **Cookie tracking is enabled:**
```php
// config/affiliates.php
'cookies' => [
    'enabled' => true,
    'auto_register_middleware' => true,
],
```

3. **Query parameter is correct:**
```php
// Default: ?aff=CODE, ?affiliate=CODE, ?ref=CODE, ?referral=CODE
'cookies' => [
    'query_parameters' => ['aff', 'affiliate', 'ref', 'referral'],
],
```

4. **Affiliate exists and is active:**
```php
$affiliate = Affiliate::where('code', 'PARTNER42')->first();
$affiliate?->isActive(); // Should be true
```

### Attribution Not Persisting

**Check:**

1. **Cookie is being set:**
   - Open browser dev tools → Application → Cookies
   - Look for `affiliate_session` cookie

2. **Cookie settings are compatible:**
```php
'cookies' => [
    'same_site' => 'lax', // Try 'none' for cross-site
    'secure' => true,     // Must be true for 'none'
],
```

3. **Consent is not blocking:**
```php
'cookies' => [
    'require_consent' => false, // Or ensure consent cookie is set
],
```

### Self-Referral Being Blocked

**Symptoms:** Affiliate's own purchases aren't being credited.

**Check:**

```php
// config/affiliates.php
'tracking' => [
    'block_self_referral' => false, // Disable if affiliates can self-refer
],
```

## Commission Issues

### Commission Not Calculated

**Symptoms:** Conversions recorded with 0 commission.

**Check:**

1. **Affiliate has commission rate set:**
```php
$affiliate->commission_rate; // Should be > 0
$affiliate->commission_type; // 'percentage' or 'fixed_amount'
```

2. **Order total is provided:**
```php
$service->recordConversion($affiliate, [
    'total_minor' => 15000, // Required for percentage commissions
]);
```

### Wrong Commission Amount

**Check:**

1. **Rate is in correct units:**
   - Percentage: basis points (1000 = 10%)
   - Fixed: minor units (1500 = $15.00)

2. **Volume tiers are configured correctly:**
```php
$affiliate->volumeTiers()->get();
```

3. **Commission rules priority:**
```php
$affiliate->commissionRules()
    ->orderBy('priority', 'desc')
    ->get();
```

## Payout Issues

### Conversions Not Appearing in Payouts

**Check:**

1. **Conversion status is Approved:**
```php
$conversion->status; // Should be ConversionStatus::Approved
```

2. **Maturity period has passed:**
```php
// config/affiliates.php
'payouts' => [
    'maturity_days' => 30,
],

// Check maturity
$conversion->occurred_at->addDays(30)->isPast();
```

3. **No active holds:**
```php
$affiliate->payoutHolds()
    ->whereNull('released_at')
    ->exists(); // Should be false
```

4. **Minimum payout threshold:**
```php
// config/affiliates.php
'payouts' => [
    'minimum_amount' => 5000, // $50.00
],

// Affiliate must have at least this amount available
$affiliate->balance->available_minor >= 5000;
```

### Payout Processing Fails

**Check:**

1. **Payout method is configured:**
```php
$affiliate->payoutMethods()
    ->where('is_verified', true)
    ->exists();
```

2. **Provider credentials are set:**
```php
// config/affiliates.php
'payouts' => [
    'paypal' => [
        'client_id' => env('AFFILIATES_PAYPAL_CLIENT_ID'), // Set?
    ],
],
```

## Multi-Tenancy Issues

### Affiliates Showing Across Tenants

**Check:**

1. **Owner mode is enabled:**
```php
// config/affiliates.php
'owner' => [
    'enabled' => true,
],
```

2. **Owner resolver is bound:**
```php
app(OwnerResolverInterface::class)->resolve(); // Should return Model
```

3. **Queries are using owner scope:**
```php
// Good
Affiliate::query()->get(); // Auto-scoped when enabled

// Bad (bypasses scope)
Affiliate::withoutOwnerScope()->get();
```

### New Records Not Getting Owner

**Check:**

1. **Auto-assign is enabled:**
```php
'owner' => [
    'auto_assign_on_create' => true,
],
```

2. **Owner context is available:**
```php
OwnerContext::resolve(); // Should return Model, not null
```

3. **Not explicitly setting null:**
```php
// This bypasses auto-assign
Affiliate::create([
    'owner_type' => null,
    'owner_id' => null,
]);
```

## Performance Issues

### Slow Attribution Queries

**Solutions:**

1. **Add indexes (already included in migrations):**
```php
$table->index(['owner_type', 'owner_id']);
$table->index(['status', 'activated_at']);
$table->index(['affiliate_id', 'status']);
```

2. **Use query caching:**
```php
$affiliate = Cache::remember(
    "affiliate:code:{$code}",
    3600,
    fn () => Affiliate::where('code', $code)->first()
);
```

### High Memory Usage in Commands

**Solutions:**

1. **Use chunking:**
```php
Affiliate::chunk(100, function ($affiliates) {
    foreach ($affiliates as $affiliate) {
        // Process
    }
});
```

2. **Disable query log in production:**
```php
DB::disableQueryLog();
```

## Fraud Detection Issues

### Too Many False Positives

**Solutions:**

1. **Tune velocity thresholds:**
```php
'fraud' => [
    'velocity' => [
        'max_clicks_per_hour' => 200,     // Increase
        'max_conversions_per_day' => 100, // Increase
    ],
],
```

2. **Adjust blocking threshold:**
```php
'fraud' => [
    'blocking_threshold' => 200, // Higher = less strict
],
```

3. **Disable specific checks:**
```php
'fraud' => [
    'velocity' => ['enabled' => false],
    'anomaly' => ['geo' => ['enabled' => false]],
],
```

## Debugging Tips

### Enable Query Logging

```php
DB::enableQueryLog();

// Run your code

dd(DB::getQueryLog());
```

### Check Event Dispatch

```php
Event::fake([AffiliateAttributed::class]);

// Run attribution

Event::assertDispatched(AffiliateAttributed::class);
```

### Test Services in Tinker

```bash
php artisan tinker
```

```php
$service = app(AffiliateService::class);
$affiliate = $service->findByCode('PARTNER42');
$affiliate->toArray();
```

### Inspect Middleware

```php
// Check if middleware is applied
Route::getRoutes()->getByName('home')->middleware();
```

## Getting Help

If you're still experiencing issues:

1. Check the [GitHub Issues](https://github.com/aiarmada/commerce/issues)
2. Review the test suite for expected behavior
3. Enable debug logging temporarily
4. Reach out on community channels
