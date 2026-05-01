<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Exports;

use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\States\DocStatus;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

final class DocExporter extends Exporter
{
    protected static ?string $model = Doc::class;

    /**
     * @param  Builder<Doc>  $query
     * @return Builder<Doc>
     */
    public static function modifyQuery(Builder $query): Builder
    {
        return $query->withSum('payments as paid_amount', 'amount');
    }

    /**
     * @return array<int, ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('doc_number')
                ->label('Document Number'),

            ExportColumn::make('doc_type')
                ->label('Type')
                ->formatStateUsing(fn (string $state): string => ucfirst($state)),

            ExportColumn::make('status')
                ->label('Status')
                ->formatStateUsing(fn (DocStatus $state): string => $state->label()),

            ExportColumn::make('customer_name')
                ->label('Customer Name')
                ->state(fn (Doc $record): string => $record->customer_data['name'] ?? ''),

            ExportColumn::make('customer_email')
                ->label('Customer Email')
                ->state(fn (Doc $record): string => $record->customer_data['email'] ?? ''),

            ExportColumn::make('issue_date')
                ->label('Issue Date'),

            ExportColumn::make('due_date')
                ->label('Due Date'),

            ExportColumn::make('currency')
                ->label('Currency'),

            ExportColumn::make('subtotal')
                ->label('Subtotal'),

            ExportColumn::make('tax_amount')
                ->label('Tax'),

            ExportColumn::make('discount_amount')
                ->label('Discount'),

            ExportColumn::make('total')
                ->label('Total'),

            ExportColumn::make('paid_amount')
                ->label('Paid Amount')
                ->state(fn (Doc $record): float => (float) ($record->paid_amount ?? 0.0)),

            ExportColumn::make('notes')
                ->label('Notes'),

            ExportColumn::make('created_at')
                ->label('Created At'),

            ExportColumn::make('updated_at')
                ->label('Updated At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your document export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
