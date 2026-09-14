<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Actions;

use AIArmada\Chip\Models\Purchase;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament-only exporter — depends on Filament\Actions\Exports\Exporter.
 * Not suitable for domain-level reuse (chip/Exports/).
 */
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
                ->formatStateUsing(fn (mixed $state): int => (int) $state),

            ExportColumn::make('payment_method')
                ->label('Payment Method'),

            ExportColumn::make('is_test')
                ->label('Test Mode')
                ->formatStateUsing(fn (mixed $state): string => $state ? 'Yes' : 'No'),

            ExportColumn::make('created_on')
                ->label('Created'),
        ];
    }

    /**
     * Exports never consult policies, so scope the query to the current
     * owner here. The signed `checkout_url` is intentionally not exported.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function modifyQuery(Builder $query): Builder
    {
        $model = $query->getModel();

        if (method_exists($model, 'scopeForOwner')) {
            return $model->scopeForOwner($query);
        }

        return $query;
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
