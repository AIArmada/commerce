<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Widgets;

use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Services\CartInstanceManager;
use Akaunting\Money\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

final class AppliedVouchersWidget extends BaseWidget
{
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getAppliedVouchersQuery())
            ->columns([
                TextColumn::make('code')
                    ->label('Voucher Code')
                    ->icon(Heroicon::OutlinedTicket)
                    ->copyable()
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'percentage' => 'Percentage',
                        'fixed' => 'Fixed Amount',
                        'free_shipping' => 'Free Shipping',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'percentage' => 'success',
                        'fixed' => 'info',
                        'free_shipping' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('discount')
                    ->label('Discount')
                    ->formatStateUsing(function (array $state): string {
                        $type = $state['type'] ?? 'fixed';
                        $value = $state['value'] ?? 0;
                        $currency = $state['currency'] ?? 'MYR';

                        return match ($type) {
                            'percentage' => number_format($value / 100, 2).' %',
                            'fixed' => Money::{$currency}($value)->format(),
                            'free_shipping' => 'Free Shipping',
                            default => 'N/A',
                        };
                    })
                    ->weight('semibold'),

                TextColumn::make('description')
                    ->label('Description')
                    ->wrap()
                    ->limit(50)
                    ->toggleable(),
            ])
            ->emptyStateHeading('No Vouchers Applied')
            ->emptyStateDescription('This cart does not have any vouchers applied yet.')
            ->emptyStateIcon(Heroicon::OutlinedTicket)
            ->actions([
                // Future: Add remove action
            ]);
    }

    protected function getTableHeading(): string
    {
        return 'Applied Vouchers';
    }

    protected function getTableDescription(): string
    {
        return 'Vouchers currently applied to this cart';
    }

    /**
     * Get query builder for applied vouchers
     */
    protected function getAppliedVouchersQuery(): mixed
    {
        if (! $this->record instanceof Cart) {
            return collect([]);
        }

        try {
            $cartInstance = app(CartInstanceManager::class)->resolve(
                $this->record->instance,
                $this->record->identifier
            );

            /** @phpstan-ignore-next-line - getAppliedVouchers method is added dynamically via proxy */
            $appliedVouchers = $cartInstance->getAppliedVouchers();

            // Transform vouchers into a collection for the table
            /** @var Collection<int, mixed> $voucherCollection */
            $voucherCollection = collect($appliedVouchers);

            return $voucherCollection->map(function ($voucher) {
                return [
                    'code' => $voucher->code ?? 'N/A',
                    'type' => $voucher->type->value ?? 'unknown',
                    'discount' => [
                        'type' => $voucher->type->value ?? 'fixed',
                        'value' => $voucher->value ?? 0,
                        'currency' => $voucher->currency ?? 'MYR',
                    ],
                    'description' => $voucher->description ?? '',
                ];
            });
        } catch (Throwable $exception) {
            // If voucher package is not properly integrated, return empty collection
            return collect([]);
        }
    }
}
