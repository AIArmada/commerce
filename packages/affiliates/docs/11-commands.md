---
title: Artisan Commands
---

# Artisan Commands

The package includes several Artisan commands for scheduled tasks and maintenance.

## Available Commands

### Aggregate Daily Stats

Aggregates attribution, conversion, and commission data into daily statistics for reporting.

```bash
php artisan affiliates:aggregate-daily-stats
```

**Options:**

```bash
# Aggregate for specific date
php artisan affiliates:aggregate-daily-stats --date=2024-01-15

# Aggregate for date range
php artisan affiliates:aggregate-daily-stats --from=2024-01-01 --to=2024-01-31

# Force re-aggregation (overwrites existing)
php artisan affiliates:aggregate-daily-stats --force
```

**Recommended Schedule:**

```php
// app/Console/Kernel.php
$schedule->command('affiliates:aggregate-daily-stats')
    ->dailyAt('02:00')
    ->withoutOverlapping();
```

### Process Commission Maturity

Moves matured commissions from holding to available balance.

```bash
php artisan affiliates:process-maturity
```

This command:
1. Finds conversions that have passed the maturity period
2. Updates their status to allow payout eligibility
3. Updates affiliate balance records

**Recommended Schedule:**

```php
$schedule->command('affiliates:process-maturity')
    ->hourly()
    ->withoutOverlapping();
```

### Process Rank Upgrades

Evaluates affiliates for rank qualification and processes upgrades.

```bash
php artisan affiliates:process-rank-upgrades
```

**Options:**

```bash
# Process specific affiliate
php artisan affiliates:process-rank-upgrades --affiliate=UUID

# Dry run (show what would change)
php artisan affiliates:process-rank-upgrades --dry-run
```

**Recommended Schedule:**

```php
$schedule->command('affiliates:process-rank-upgrades')
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
# Process specific payout method only
php artisan affiliates:process-payouts --method=paypal

# Limit number of payouts processed
php artisan affiliates:process-payouts --limit=100

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

Exports payout data to CSV or other formats for external processing.

```bash
php artisan affiliates:export-payouts
```

**Options:**

```bash
# Export date range
php artisan affiliates:export-payouts --from=2024-01-01 --to=2024-01-31

# Export specific status
php artisan affiliates:export-payouts --status=completed

# Output format
php artisan affiliates:export-payouts --format=csv
php artisan affiliates:export-payouts --format=json

# Output path
php artisan affiliates:export-payouts --output=/path/to/payouts.csv
```

## Recommended Schedule Configuration

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule): void
{
    // Aggregate stats after midnight
    $schedule->command('affiliates:aggregate-daily-stats')
        ->dailyAt('02:00')
        ->withoutOverlapping()
        ->runInBackground();

    // Process commission maturity hourly
    $schedule->command('affiliates:process-maturity')
        ->hourly()
        ->withoutOverlapping();

    // Process rank upgrades daily
    $schedule->command('affiliates:process-rank-upgrades')
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

For multi-tenant applications, commands process all tenants by default. To scope to specific tenants:

```bash
# Process specific tenant
php artisan affiliates:aggregate-daily-stats --tenant=UUID
```

Or iterate through tenants in your schedule:

```php
$schedule->call(function () {
    foreach (Tenant::all() as $tenant) {
        Artisan::call('affiliates:aggregate-daily-stats', [
            '--tenant' => $tenant->id,
        ]);
    }
})->daily();
```
