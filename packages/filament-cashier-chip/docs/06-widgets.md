---
title: Dashboard Widgets
---

# Dashboard Widgets

Analytics widgets for monitoring subscription metrics on your Filament dashboard.

## Available Widgets

### MRRWidget

Monthly Recurring Revenue with trend analysis.

**Displays:**
- Current MRR value
- Percentage change from last month
- 6-month sparkline chart

**Calculation:**
- Sums `unit_amount` across a subscription's items (`withSum('items', 'unit_amount')`), multiplied
  by the subscription `quantity`
- Normalizes to monthly (day × 30, week × 4.33, month ÷ 1, year ÷ 12, each further ÷ the interval count)
- Subtracts the coupon discount, normalized the same way
- Never returns a negative value

```php
// MRR calculation (inside the widget)
$monthlyAmount = $this->normalizeToMonthly(
    (int) $subscription->items_sum_unit_amount * (int) $subscription->quantity,
    $subscription->billing_interval ?? 'month',
    $subscription->billing_interval_count ?? 1,
);
```

> **warning**
> Amounts stay in integer minor units. The 6-month sparkline in `getMRRChart()` is the one place that
> divides by 100 before charting, so it assumes a 2-decimal currency.

### ActiveSubscribersWidget

Total count of active subscribers.

**Displays:**
- Current active subscriber count
- Comparison with the previous month
- Trend indicator (up/down/stable)
- 6-month sparkline

**Counts subscriptions with status:**
- Active (`scopeWhereActive`)
- Trialing (`scopeWhereOnTrial`)

Past Due subscriptions are **not** counted.

### ChurnRateWidget

Monthly churn rate percentage.

**Displays:**
- Current churn rate
- Month-over-month comparison
- Trend indicator

**Calculation:**
```
Churn Rate = (Subscriptions with ends_at in this calendar month
              / Subscriptions created before the month, not ended, active or trialing) × 100
```

### TrialConversionsWidget

Trial-to-paid conversion rate.

**Displays two stats:**
- `Trial Conversion Rate` (percentage) with month-over-month comparison
- `Active Trials` (current `scopeOnTrial` count)

**Calculation:**
```
Conversion Rate = (Trials whose trial_ends_at fell in this calendar month AND
                   chip_status = 'active' AND ends_at IS NULL
                   / All trials whose trial_ends_at fell in this calendar month) × 100
```

### AttentionRequiredWidget

Count of subscriptions needing attention.

**Displays:**
- One stat: the total of all attention buckets, with a breakdown in the description
- Trend icon switches between `ExclamationTriangle` (non-zero) and `CheckCircle` (zero)

**Buckets (computed in one grouped query):**
- Trials ending within the next 3 days
- Subscriptions in `past_due`
- Grace periods (`ends_at`) ending within the next 3 days
- Subscriptions in `incomplete`
- Subscriptions in `unpaid`

The stat has no URL — it does not link to a filtered list. Renewal attempts are not consulted.

### RevenueChartWidget

Revenue trend visualization over time.

**Displays:**
- Line chart (`getType()` returns `'line'`)
- Two datasets: `MRR` and `New Revenue`
- 12 months of history, `columnSpan = 'full'`, `pollingInterval = '120s'`

### SubscriptionDistributionWidget

Subscriptions grouped by **status**, not by plan.

**Displays:**
- Doughnut chart (`getType()` returns `'doughnut'`)
- Buckets: Active, Trialing, Canceled, Past Due, Paused, Incomplete
- Zero-count buckets are omitted from labels and data
- `columnSpan = 1`, `pollingInterval = '120s'`

## Enabling Widgets

### All Widgets

```php
FilamentCashierChipPlugin::make()
    ->dashboardWidgets(true);
```

### Individual Widgets

Via config:

```php
// config/filament-cashier-chip.php
'features' => [
    'dashboard' => [
        'widgets' => [
            'mrr' => true,
            'active_subscribers' => true,
            'churn_rate' => true,
            'attention_required' => true,
            'revenue_chart' => true,
            'subscription_distribution' => true,
            'trial_conversions' => true,
        ],
    ],
],
```

### On Specific Dashboard

Register widgets manually:

```php
use AIArmada\FilamentCashierChip\Widgets\MRRWidget;
use AIArmada\FilamentCashierChip\Widgets\ActiveSubscribersWidget;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected function getWidgets(): array
    {
        return [
            MRRWidget::class,
            ActiveSubscribersWidget::class,
        ];
    }
}
```

## Widget Configuration

### Sort Order

```php
// In the widget class
protected static ?int $sort = 1;
```

Default sort order:
1. `MRRWidget` (`$sort = 1`)
2. `ActiveSubscribersWidget` (`$sort = 2`)
3. `ChurnRateWidget` (`$sort = 3`)
4. `RevenueChartWidget` (`$sort = 4`)
5. `SubscriptionDistributionWidget` (`$sort = 5`)
6. `TrialConversionsWidget` (`$sort = 6`)
7. `AttentionRequiredWidget` (`$sort = 7`)

### Column Span

Stats widgets override `getColumns()` (a `StatsOverviewWidget` API):

```php
protected function getColumns(): int
{
    return 1;
}
```

`MRRWidget`, `ActiveSubscribersWidget`, `ChurnRateWidget`, and `AttentionRequiredWidget` return 1;
`TrialConversionsWidget` returns 2 because it renders two stats.

Chart widgets extend `Filament\Widgets\ChartWidget`, which has no `getColumns()`. They set
`$columnSpan` instead:

```php
protected int | string | array $columnSpan = 'full'; // RevenueChartWidget
protected int | string | array $columnSpan = 1;      // SubscriptionDistributionWidget
```

## Customizing Widgets

> **warning**
> All seven widgets are declared `final`, so `extends MRRWidget` (or any of the others) is a fatal
> error. To customize, write your own widget and reuse the shared data concern:

```php
namespace App\Filament\Widgets;

use AIArmada\CashierChip\Subscription\Subscription;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TargetWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 0; // First widget

    protected function getStats(): array
    {
        $mrr = Subscription::query()
            ->whereActive()
            ->withSum('items', 'unit_amount')
            ->get()
            ->sum(fn (Subscription $s): int => (int) ($s->items_sum_unit_amount ?? 0) * (int) ($s->quantity ?? 1));

        return [
            Stat::make('MRR vs Target', $mrr)
                ->description('Monthly goal'),
        ];
    }
}
```

> **warning**
> There is no `monthly_amount` column. MRR is the `withSum('items', 'unit_amount')` sub-select times
> `quantity`, normalized per `billing_interval` and `billing_interval_count`, minus the normalized
> coupon discount. Copy the real `MRRWidget::monthlyNetAmount()` logic rather than summing a
> non-existent column. `whereActive()` is a valid `scopeWhereActive` call, not a macro.

`AIArmada\FilamentCashierChip\Concerns\InteractsWithCashierChipData` is the reusable piece:
`subscriptionQuery()` (owner-scoped), `normalizeToMonthly()`, `formatCurrency()`, and
`rememberWidgetValue()`.

## Owner Scoping

All widgets automatically apply owner scoping and are hidden when owner
scoping is enabled but no owner is resolved, so admin dashboards never
render misleading global-only zeros.

This ensures:
- Multi-tenant data isolation
- Each tenant sees only their metrics
- Consistent with resource scoping

## Performance Considerations

### Caching

Widget aggregates are cached per owner via `OwnerCache`. Tune the TTL:

```php
'widgets' => [
    'cache_ttl' => 120, // seconds
],
```

### Query Optimization

Widgets compute aggregates in SQL (grouped counts, conditional sums)
and select only the columns they need instead of hydrating full models.

### Polling

The four stats widgets do not poll. The two chart widgets set a non-static `$pollingInterval` of
`'120s'`. To add live updates to your own widget, use the same non-static form:

```php
protected ?string $pollingInterval = '30s';
```

> **warning**
> `protected static ?string $pollingInterval` is silently ignored in Filament v5 — `HasPolling`
> resolves `$pollingInterval` on the instance, not the class.

## Chart Widgets

### Revenue Chart

`getData()` returns Chart.js datasets plus labels, alongside `getType()` and `getOptions()`:

```php
protected function getData(): array
{
    return [
        'datasets' => [
            [
                'label' => 'MRR',
                'data' => $this->getMonthlyRevenue(),
                'borderColor' => 'rgb(59, 130, 246)',
                'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                'fill' => true,
            ],
        ],
        'labels' => $this->getMonthLabels(),
    ];
}
```

### Subscription Distribution

Grouped by `chip_status` in SQL, not by plan name:

```php
protected function getData(): array
{
    return [
        'datasets' => [
            [
                'data' => $counts,   // one count per non-zero status bucket
                'backgroundColor' => $this->getColors(),
            ],
        ],
        'labels' => $labels,        // 'Active', 'Trialing', 'Canceled', …
    ];
}
```

## Testing Widgets

```php
use AIArmada\FilamentCashierChip\Widgets\MRRWidget;
use Livewire\Livewire;

it('displays MRR widget', function () {
    // Create test subscriptions
    Subscription::factory()->active()->create([
        'billing_interval' => 'month',
    ]);
    
    Livewire::test(MRRWidget::class)
        ->assertSee('Monthly Recurring Revenue');
});
```

## Next Steps

- [Usage](04-usage.md) – Admin panel resources
- [Billing Portal](05-billing-portal.md) – Customer self-service
