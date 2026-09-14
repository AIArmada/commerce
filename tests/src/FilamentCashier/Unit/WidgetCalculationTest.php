<?php

declare(strict_types=1);

use AIArmada\Cashier\CashierServiceProvider;
use AIArmada\CashierChip\Billing\Cashier as CashierChip;
use AIArmada\CashierChip\Enums\SubscriptionStatus as ChipSubscriptionStatus;
use AIArmada\CashierChip\Subscription\Subscription as ChipSubscription;
use AIArmada\CashierChip\Subscription\SubscriptionItem as ChipSubscriptionItem;
use AIArmada\Commerce\Tests\FilamentCashier\Fixtures\ChipBillableUser;
use AIArmada\FilamentCashier\Widgets\GatewayComparisonWidget;
use AIArmada\FilamentCashier\Widgets\TotalMrrWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    app()->register(CashierServiceProvider::class);

    config()->set('cashier.gateways', [
        'chip' => [
            'driver' => 'chip',
            'label' => 'CHIP',
            'icon' => 'heroicon-o-cube',
            'color' => 'emerald',
            'dashboard_url' => 'https://gate.chip-in.asia',
        ],
    ]);

    CashierChip::useSubscriptionModel(ChipSubscription::class);
    CashierChip::useSubscriptionItemModel(ChipSubscriptionItem::class);
});

function createChipSubscriptionWithAmount(array $overrides, int $unitAmount): ChipSubscription
{
    $billable = ChipBillableUser::query()->create([
        'name' => 'Widget User',
        'email' => 'widget-' . Str::uuid() . '@example.com',
        'password' => bcrypt('secret'),
    ]);

    $subscription = ChipSubscription::query()->create(array_merge([
        'id' => (string) Str::uuid(),
        'billable_type' => $billable->getMorphClass(),
        'billable_id' => (string) $billable->getKey(),
        'type' => 'default',
        'chip_id' => 'sub_' . Str::random(8),
        'chip_status' => ChipSubscriptionStatus::Active,
        'chip_price' => 'price_basic_monthly',
        'quantity' => 1,
        'billing_interval' => 'month',
        'billing_interval_count' => 1,
        'ends_at' => null,
    ], $overrides));

    $item = new ChipSubscriptionItem([
        'subscription_id' => $subscription->getKey(),
        'chip_product' => 'prod_widget',
        'chip_price' => 'price_basic_monthly',
        'quantity' => 1,
    ]);
    $item->chip_id = 'si_' . Str::random(8);
    $item->unit_amount = $unitAmount;
    $item->save();

    if (array_key_exists('created_at', $overrides)) {
        ChipSubscription::query()->whereKey($subscription->getKey())->update([
            'created_at' => $overrides['created_at'],
        ]);
    }

    return $subscription->fresh();
}

it('converts foreign currency amounts through the base rate', function (): void {
    $method = new ReflectionMethod(TotalMrrWidget::class, 'convertAmountToBase');
    $widget = app(TotalMrrWidget::class);

    $rates = ['MYR' => 4.70, 'USD' => 1.00];

    expect($method->invoke($widget, 10000, 'USD', 'MYR', $rates))->toBe(47000)
        ->and($method->invoke($widget, 47000, 'MYR', 'USD', $rates))->toBe(10000)
        ->and($method->invoke($widget, 10000, 'MYR', 'MYR', $rates))->toBe(0)
        ->and($method->invoke($widget, 10000, 'EUR', 'MYR', $rates))->toBe(0)
        ->and($method->invoke($widget, 10000, 'USD', 'MYR', ['MYR' => 4.70, 'USD' => 0]))->toBe(0);
});

it('buckets six months of revenue in a single scan per gateway', function (): void {
    $twoMonthsAgo = now()->subMonths(2)->startOfMonth()->addDay();
    $thisMonth = now()->startOfMonth()->addDay();

    createChipSubscriptionWithAmount(['created_at' => $twoMonthsAgo], 1000);
    createChipSubscriptionWithAmount(['created_at' => $thisMonth], 2500);
    createChipSubscriptionWithAmount([
        'created_at' => $thisMonth,
        'ends_at' => now()->endOfMonth(),
    ], 9999);

    $method = new ReflectionMethod(GatewayComparisonWidget::class, 'getData');

    DB::enableQueryLog();
    $data = $method->invoke(app(GatewayComparisonWidget::class));
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $scanQueries = array_values(array_filter(
        $queries,
        fn (array $query): bool => str_contains($query['query'], 'cashier_chip_subscriptions')
            && ! str_contains($query['query'], 'subscription_items'),
    ));

    expect($scanQueries)->toHaveCount(1);

    $series = $data['datasets'][0]['data'];

    expect($series)->toHaveCount(6)
        ->and($series[3])->toBe(10.0)
        ->and($series[5])->toBe(25.0)
        ->and(array_sum($series))->toBe(35.0);
});
