<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Widgets;

use AIArmada\Vouchers\Models\Voucher;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Lazy;
use Throwable;

/**
 * Widget showing cart usage statistics for a voucher
 */
#[Lazy]
final class VoucherCartStatsWidget extends BaseWidget
{
    public ?Model $record = null;

    protected function getStats(): array
    {
        if (! $this->record instanceof Voucher) {
            return [];
        }

        $stats = [];

        // Active carts count (if filament-cart is available)
        if (class_exists(\AIArmada\FilamentCart\Models\Cart::class)) {
            $activeCarts = $this->getActiveCartsCount();

            $stats[] = Stat::make('Active Carts', $activeCarts)
                ->description('Carts with this voucher currently applied')
                ->descriptionIcon(Heroicon::OutlinedShoppingCart)
                ->color($activeCarts > 0 ? 'success' : 'gray');
        }

        // Total redemptions
        $totalRedemptions = $this->record->usages()->count();
        $stats[] = Stat::make('Total Redemptions', $totalRedemptions)
            ->description('Number of times this voucher has been used')
            ->descriptionIcon(Heroicon::OutlinedCheckCircle)
            ->color($totalRedemptions > 0 ? 'info' : 'gray');

        // Remaining uses (if usage limit is set)
        if ($this->record->usage_limit !== null) {
            $remaining = $this->record->getRemainingUses();
            $stats[] = Stat::make('Remaining Uses', $remaining ?? '∞')
                ->description('Available redemptions left')
                ->descriptionIcon(Heroicon::OutlinedTicket)
                ->color($remaining > 0 ? 'success' : 'danger');
        }

        return $stats;
    }

    /**
     * Get count of active carts using this voucher
     */
    protected function getActiveCartsCount(): int
    {
        if (! $this->record instanceof Voucher) {
            return 0;
        }

        try {
            /** @var Voucher $voucher */
            $voucher = $this->record;

            /** @var class-string<\AIArmada\FilamentCart\Models\Cart> $cartModel */
            $cartModel = \AIArmada\FilamentCart\Models\Cart::class;

            // Search for this voucher code in cart conditions metadata
            // Vouchers are stored as conditions with the voucher code in metadata
            return $cartModel::query()
                ->whereNotNull('conditions')
                ->where(function ($query) use ($voucher): void {
                    $query->whereJsonContains('conditions', ['voucher' => $voucher->code])
                        ->orWhereRaw('conditions::text LIKE ?', ['%"code":"'.$voucher->code.'"%']);
                })
                ->count();
        } catch (Throwable $exception) {
            // If query fails, return 0
            return 0;
        }
    }
}
