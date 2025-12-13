<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Actions;

use AIArmada\Chip\Models\SendInstruction;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

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

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your payout export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
