<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Actions;

use AIArmada\Chip\Models\Purchase;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class PurchaseExporter extends Exporter
{
    protected static ?string $model = Purchase::class;

    /**
     * @return array<ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('Purchase ID'),

            ExportColumn::make('reference')
                ->label('Reference'),

            ExportColumn::make('status')
                ->label('Status'),

            ExportColumn::make('client_id')
                ->label('Client ID'),

            ExportColumn::make('purchase.total')
                ->label('Amount (cents)')
                ->formatStateUsing(function ($state): int {
                    if (is_array($state)) {
                        return (int) ($state['amount'] ?? 0);
                    }

                    return (int) $state;
                }),

            ExportColumn::make('payment_method')
                ->label('Payment Method'),

            ExportColumn::make('is_test')
                ->label('Test Mode')
                ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No'),

            ExportColumn::make('created_on')
                ->label('Created'),

            ExportColumn::make('checkout_url')
                ->label('Checkout URL'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your purchase export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
