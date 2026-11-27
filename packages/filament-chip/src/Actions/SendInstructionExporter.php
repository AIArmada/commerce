<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Actions;

use AIArmada\Chip\Features\Payouts\Models\SendInstruction;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament-only exporter — depends on Filament\Actions\Exports\Exporter.
 * Not suitable for domain-level reuse (chip/Exports/).
 */
class SendInstructionExporter extends Exporter
{
    protected static ?string $model = SendInstruction::class;

    /**
     * @return array<ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('Instruction ID'),

            ExportColumn::make('reference')
                ->label('Reference'),

            ExportColumn::make('state')
                ->label('Status'),

            ExportColumn::make('bank_account_id')
                ->label('Bank Account ID'),

            ExportColumn::make('amount')
                ->label('Amount (MYR)'),

            ExportColumn::make('email')
                ->label('Notification Email'),

            ExportColumn::make('description')
                ->label('Description'),

            ExportColumn::make('slug')
                ->label('Slug'),

            ExportColumn::make('receipt_url')
                ->label('Receipt URL'),

            ExportColumn::make('created_at')
                ->label('Created'),
        ];
    }

    /**
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

        return $query->whereRaw('1 = 0');
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your payout export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
