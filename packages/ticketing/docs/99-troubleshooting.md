---
title: Troubleshooting
---

# Troubleshooting

## Common Issues

### Table Name Collisions

**Problem**: Existing `ticket_types` or `passes` tables conflict with the package.

**Solution**: Override table names via environment variables:

```bash
# .env
TICKETING_TICKET_TYPES_TABLE=my_ticket_types
TICKETING_PASSES_TABLE=my_passes
```

Or set the table prefix:

```bash
TICKETING_TABLE_PREFIX=event_
```

### Missing Holder Email on New Passes

**Problem**: Pass notifications are not sent.

**Solution**: The issue actions set holder email from `PassIssuanceContext->holderAttributes`. If null, the delivery service skips sending. Always populate `name` and `email` per holder:

```php
$context = new PassIssuanceContext(
    ticketType: $ticketType,
    quantity: 1,
    holderAttributes: [
        ['name' => 'Alice', 'email' => 'alice@example.com'],
    ],
);
```

### Pass Won't Transfer

**Problem**: `$pass->canTransfer()` returns false.

**Solution**: Check the pass is in a non-terminal state:

```php
// Must not be Used, Revoked, Voided, or Expired
if ($pass->state->isTerminal()) {
    // Cannot transfer terminal passes
}

// Must not be past transfer deadline
if ($pass->transferExpired()) {
    // Transfer window has closed
}
```

Check `transfer_expires_at` on the ticketable model:

```php
// The ticketable model's transferWindowEndsAt() is the source of truth
$deadline = $workshop->transferWindowEndsAt();
```

### Pricing Consistency Errors

**Problem**: Order validation fails due to pricing mismatch.

**Solution**: The `pricing_consistency_check` feature validates ticket prices against order line items. You can disable it:

```bash
TICKETING_PRICING_CONSISTENCY_CHECK=false
```

Or ensure prices match exactly between ticket type and order.

### JSON Column Errors on PostgreSQL

**Problem**: Migration errors with JSON columns on PostgreSQL.

**Solution**: Set the correct column type:

```php
// config/ticketing.php
'database' => [
    'json_column_type' => 'jsonb', // For PostgreSQL
],
```

Then refresh:

```bash
php artisan migrate:refresh
```

### Owner Scoping Issues

**Problem**: Passes not showing in queries.

**Solution**: Ensure owner context is set:

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

OwnerContext::withOwner($tenant, function () {
    $passes = Pass::all(); // Scoped to tenant
});
```

**Problem**: Cross-tenant access blocked.

**Solution**: Verify the pass owner matches the related owner-aware model:

```php
if ($pass->belongsToOwner($order->owner)) {
    // Safe to proceed
}
```

### Bulk Transfer Fails

**Problem**: Bulk transfer exceeds limit.

**Solution**: The default max is 100. Increase it:

```bash
TICKETING_BULK_TRANSFER_MAX=500
```

Or split into smaller batches:

```php
foreach (array_chunk($passIds, 100) as $batch) {
    app(BulkTransferPassesAction::class)->handle(
        passIds: $batch,
        newHolder: $newHolder,
        reason: 'Batch transfer',
    );
}
```

### Auto-Issue Not Firing

**Problem**: Passes not auto-issuing on order paid.

**Solution**: Verify `aiarmada/orders` is installed and auto-issue is enabled:

```bash
composer show aiarmada/orders
```

Check config:

```php
// Must be true
$enabled = config('ticketing.features.auto_issue_passes');
```

Check the listener is registered:

```bash
php artisan event:list | grep IssuePassesOnOrderPaid
```

### Migration Issues

**Problem**: `php artisan migrate` fails with "table already exists".

**Solution**: If you have existing tables from a previous install, publish the config first and set custom table names before running migrations:

```bash
php artisan vendor:publish --tag="ticketing-config"
# Edit config/ticketing.php to set custom table names
php artisan migrate
```

## Debug Mode

Enable detailed logging:

```php
// config/logging.php
'channels' => [
    'ticketing' => [
        'driver' => 'daily',
        'path' => storage_path('logs/ticketing.log'),
        'level' => 'debug',
    ],
],
```

Log ticketing operations:

```php
use Illuminate\Support\Facades\Log;

Log::channel('ticketing')->debug('Issuing pass', [
    'ticket_type_id' => $ticketType->getKey(),
    'quantity' => $context->quantity,
    'owner' => OwnerContext::resolve()?->getKey(),
]);
```

## Getting Help

1. **Check Configuration**: Review `config/ticketing.php`
2. **Enable Debug Mode**: Set `APP_DEBUG=true` in `.env`
3. **Check Logs**: Review `storage/logs/laravel.log`
4. **Test in Isolation**: Create minimal reproduction case
5. **Check Package Version**: `composer show aiarmada/ticketing`

## Reporting Issues

When reporting issues, include:

- Laravel version
- PHP version
- Package version
- Configuration (sanitized)
- Error message with stack trace
- Steps to reproduce
- Expected vs actual behavior

## Common Gotchas

### Owner Scoping
- **Always** validate owner context in multi-tenant mode
- **Never** trust UI-provided IDs without server-side validation
- **Use** `forOwner()` explicitly when owner context is ambiguous

### State Machine
- **Terminal** states (Used, Revoked, Voided, Expired) cannot transition
- **Cancelled** passes cannot be re-issued — create a new pass instead
- **Pending** passes must be issued before they can be used

### Transfers
- **Bulk** transfers respect `bulk_max_size` — batch larger operations
- **Grace** period extends the transfer window in seconds
- **Notifications** require holder email to be set

### Pricing
- **All** prices are in minor units (e.g., cents/sen)
- **Components** must sum to the total price
- **Currency** is per-ticket-type, not inherited

## Read next

- [Configuration](03-configuration.md) — Review configuration options
- [Usage](04-usage.md) — Review usage patterns
