<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Models\Order;
use AIArmada\Products\Models\Product;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\VoucherStatus;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

final class StatsOverview extends BaseWidget
{
    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $owner = OwnerContext::resolve();

        if ($owner === null) {
            return [
                Stat::make('Total Revenue', 'RM '.Number::format(0, 2))
                    ->description('From paid orders')
                    ->descriptionIcon('heroicon-m-banknotes')
                    ->color('success')
                    ->chart([0, 0, 0, 0, 0, 0, 0]),

                Stat::make('Pending Orders', 0)
                    ->description('Awaiting processing')
                    ->descriptionIcon('heroicon-m-clock')
                    ->color('success'),

                Stat::make('Products', 0)
                    ->description('Inventory healthy')
                    ->descriptionIcon('heroicon-m-cube')
                    ->color('info'),

                Stat::make('Active Vouchers', 0)
                    ->description('0 redemptions')
                    ->descriptionIcon('heroicon-m-ticket')
                    ->color('primary'),

                Stat::make('Affiliates', 0)
                    ->description('RM '.Number::format(0, 2).' pending')
                    ->descriptionIcon('heroicon-m-users')
                    ->color('success'),

                Stat::make('Customers', 0)
                    ->description('0 with orders')
                    ->descriptionIcon('heroicon-m-user-group')
                    ->color('info'),
            ];
        }

        // Calculate revenue
        $totalRevenue = Order::query()->forOwner($owner)->whereNotNull('paid_at')->sum('grand_total');
        $pendingOrders = Order::query()->forOwner($owner)->whereNull('paid_at')->count();

        $uniqueCustomersWithOrders = Order::query()
            ->forOwner($owner)
            ->whereNotNull('customer_id')
            ->distinct('customer_id')
            ->count('customer_id');

        // Voucher stats
        $activeVouchers = Voucher::query()->forOwner($owner)->where('status', VoucherStatus::normalize(Active::class))->count();
        $voucherRedemptions = VoucherUsage::query()
            ->whereIn('voucher_id', Voucher::query()->forOwner($owner)->select('id'))
            ->count();

        // Affiliate stats
        $activeAffiliates = Affiliate::query()->forOwner($owner)->where('status', AffiliateStatus::Active)->count();
        $pendingCommissions = AffiliateConversion::query()
            ->whereIn('affiliate_id', Affiliate::query()->forOwner($owner)->select('id'))
            ->where('status', ConversionStatus::Pending)
            ->sum('commission_minor');

        $lowStockProducts = 0;

        return [
            Stat::make('Total Revenue', 'RM '.Number::format($totalRevenue / 100, 2))
                ->description('From paid orders')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart([7, 12, 8, 15, 18, 22, 25]),

            Stat::make('Pending Orders', $pendingOrders)
                ->description('Awaiting processing')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingOrders > 0 ? 'warning' : 'success'),

            Stat::make('Products', Product::query()->forOwner($owner)->count())
                ->description($lowStockProducts > 0 ? $lowStockProducts.' low inventory' : 'Inventory healthy')
                ->descriptionIcon($lowStockProducts > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-cube')
                ->color($lowStockProducts > 0 ? 'warning' : 'info'),

            Stat::make('Active Vouchers', $activeVouchers)
                ->description($voucherRedemptions.' redemptions')
                ->descriptionIcon('heroicon-m-ticket')
                ->color('primary'),

            Stat::make('Affiliates', $activeAffiliates)
                ->description('RM '.Number::format($pendingCommissions / 100, 2).' pending')
                ->descriptionIcon('heroicon-m-users')
                ->color('success'),

            Stat::make('Customers', $uniqueCustomersWithOrders)
                ->description($uniqueCustomersWithOrders.' with orders')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),
        ];
    }
}
