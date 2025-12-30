<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Orders\Models\Order;
use AIArmada\Products\Models\Product;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

final class StatsOverview extends BaseWidget
{
    protected static ?int $sort = -3;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        // Calculate revenue
        $totalRevenue = Order::whereNotNull('paid_at')->sum('grand_total');
        $pendingOrders = Order::whereNull('paid_at')->count();

        // Voucher stats
        $activeVouchers = Voucher::where('status', VoucherStatus::Active)->count();
        $voucherRedemptions = VoucherUsage::count();

        // Affiliate stats
        $activeAffiliates = Affiliate::where('status', AffiliateStatus::Active)->count();
        $pendingCommissions = AffiliateConversion::where('status', ConversionStatus::Pending)->sum('commission_minor');

        $lowStockProducts = 0;

        return [
            Stat::make('Total Revenue', 'RM ' . Number::format($totalRevenue / 100, 2))
                ->description('From paid orders')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart([7, 12, 8, 15, 18, 22, 25]),

            Stat::make('Pending Orders', $pendingOrders)
                ->description('Awaiting processing')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingOrders > 0 ? 'warning' : 'success'),

            Stat::make('Products', Product::count())
                ->description($lowStockProducts > 0 ? $lowStockProducts . ' low inventory' : 'Inventory healthy')
                ->descriptionIcon($lowStockProducts > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-cube')
                ->color($lowStockProducts > 0 ? 'warning' : 'info'),

            Stat::make('Active Vouchers', $activeVouchers)
                ->description($voucherRedemptions . ' redemptions')
                ->descriptionIcon('heroicon-m-ticket')
                ->color('primary'),

            Stat::make('Affiliates', $activeAffiliates)
                ->description('RM ' . Number::format($pendingCommissions / 100, 2) . ' pending')
                ->descriptionIcon('heroicon-m-users')
                ->color('success'),

            Stat::make('Customers', User::count())
                ->description(Order::distinct('customer_id')->count() . ' with orders')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),
        ];
    }
}
