---
title: Artisan Commands
---

# Artisan Commands

The package includes several Artisan commands for scheduled tasks and maintenance.

## Available Commands

### Aggregate Daily Stats

Aggregates attribution, conversion, and commission data into daily statistics for reporting.

```bash
php artisan affiliates:aggregate-daily
```

**Options:**

```bash
# Aggregate for specific date
php artisan affiliates:aggregate-daily --date=2024-01-15

# Backfill from the start of data
php artisan affiliates:aggregate-daily --backfill

# Backfill a bounded range
php artisan affiliates:aggregate-daily --backfill --from=2024-01-01 --to=2024-01-31
```

**Recommended Schedule:**

```php
// app/Console/Kernel.php
$schedule->command('affiliates:aggregate-daily')
    ->dailyAt('02:00')
    ->withoutOverlapping();
```

### Process Commission Maturity

Moves matured commissions from holding to available balance.

```bash
php artisan affiliates:process-maturity
```

This command:
1. Finds qualified conversions that have passed the maturity period
2. Promotes them to the approved, payout-eligible state
3. Updates affiliate balance records

When owner mode is enabled and no owner context is already active, the command iterates each owner context automatically.

**Recommended Schedule:**

```php
$schedule->command('affiliates:process-maturity')
    ->hourly()
    ->withoutOverlapping();
```

### Process Rank Upgrades

Evaluates affiliates for rank qualification and processes upgrades.

```bash
php artisan affiliates:process-ranks
```

**Options:**

```bash
# Dry run (show what would change)
php artisan affiliates:process-ranks --dry-run
```

**Recommended Schedule:**

```php
$schedule->command('affiliates:process-ranks')
    ->daily()
    ->withoutOverlapping();
```

### Process Scheduled Payouts

Processes payouts that are scheduled for the current date.

```bash
php artisan affiliates:process-payouts
```

**Options:**

```bash
# Process a single affiliate
php artisan affiliates:process-payouts --affiliate=UUID

# Override the minimum amount threshold
php artisan affiliates:process-payouts --min-amount=10000

# Dry run
php artisan affiliates:process-payouts --dry-run
```

**Recommended Schedule:**

```php
$schedule->command('affiliates:process-payouts')
    ->dailyAt('06:00')
    ->withoutOverlapping();
```

### Export Affiliate Payouts

Exports a single payout batch, including its linked conversions, to CSV for external processing.

```bash
php artisan affiliates:payout:export PAY-REF-1234
```

**Options:**

```bash
# Export by payout UUID instead of reference
php artisan affiliates:payout:export 2d8ce0f4-0000-0000-0000-000000000000

# Save to a custom path
php artisan affiliates:payout:export PAY-REF-1234 --path=/path/to/payout.csv
```

## Recommended Schedule Configuration

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule): void
{
    // Aggregate stats after midnight
    $schedule->command('affiliates:aggregate-daily')
        ->dailyAt('02:00')
        ->withoutOverlapping()
        ->runInBackground();

    // Process commission maturity hourly
    $schedule->command('affiliates:process-maturity')
        ->hourly()
        ->withoutOverlapping();

    // Process rank upgrades daily
    $schedule->command('affiliates:process-ranks')
        ->dailyAt('03:00')
        ->withoutOverlapping();

    // Process scheduled payouts in the morning
    $schedule->command('affiliates:process-payouts')
        ->dailyAt('06:00')
        ->withoutOverlapping()
        ->emailOutputOnFailure('admin@example.com');
}
```

## Multi-Tenant Considerations

When `affiliates.owner.enabled` is `true`, the built-in commands automatically iterate owner contexts if they are started without an explicit owner already resolved.

That means you usually do **not** need a custom `--tenant` flag wrapper for the built-in commands.

If you are orchestrating package-specific work manually, prefer explicit owner context in your own callback or queued job:

```php
OwnerContext::withOwner($tenant, function (): void {
    Artisan::call('affiliates:aggregate-daily');
});
```
