<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Resources\UnifiedInvoiceResource\Tables;

use AIArmada\Cashier\Support\GatewayDetector;
use AIArmada\Cashier\Support\InvoiceStatus;
use AIArmada\Cashier\Support\UnifiedInvoice;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        $gatewayDetector = app(GatewayDetector::class);

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label(__('filament-cashier::invoices.table.number'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('userId')
                    ->label(__('filament-cashier::invoices.table.customer'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('gateway')
                    ->label(__('filament-cashier::invoices.table.gateway'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $gatewayDetector->getLabel($state))
                    ->color(fn (string $state): string => $gatewayDetector->getColor($state))
                    ->icon(fn (string $state): string => $gatewayDetector->getIcon($state)),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('filament-cashier::invoices.table.status'))
                    ->badge()
                    ->formatStateUsing(fn (InvoiceStatus $state): string => $state->label())
                    ->color(fn (InvoiceStatus $state): string => $state->color())
                    ->icon(fn (InvoiceStatus $state): string => $state->icon()),

                Tables\Columns\TextColumn::make('formattedAmount')
                    ->label(__('filament-cashier::invoices.table.amount'))
                    ->getStateUsing(fn (UnifiedInvoice $record): string => $record->formattedAmount()),

                Tables\Columns\TextColumn::make('date')
                    ->label(__('filament-cashier::invoices.table.date'))
                    ->date(config('filament-cashier.tables.date_format', 'M d, Y'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('paidAt')
                    ->label(__('filament-cashier::invoices.table.paid_at'))
                    ->date(config('filament-cashier.tables.date_format', 'M d, Y'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('gateway')
                    ->label(__('filament-cashier::invoices.table.gateway'))
                    ->options($gatewayDetector->getGatewayOptions()),

                Tables\Filters\SelectFilter::make('status')
                    ->label(__('filament-cashier::invoices.table.status'))
                    ->options(
                        collect(InvoiceStatus::cases())
                            ->mapWithKeys(fn (InvoiceStatus $status) => [$status->value => $status->label()])
                            ->toArray()
                    ),
            ])
            ->actions([
                Action::make('download')
                    ->label(__('filament-cashier::invoices.actions.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (UnifiedInvoice $record): ?string => $record->pdfUrl)
                    ->openUrlInNewTab()
                    ->visible(fn (UnifiedInvoice $record): bool => $record->pdfUrl !== null),

                Action::make('view_external')
                    ->label(fn (UnifiedInvoice $record): string => __('filament-cashier::invoices.actions.view_external', [
                        'gateway' => $record->gatewayConfig()['label'],
                    ]))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (UnifiedInvoice $record): string => $record->externalDashboardUrl())
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('export')
                        ->label(__('filament-cashier::subscriptions.bulk.export'))
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (Collection $records): StreamedResponse {
                            return response()->streamDownload(function () use ($records): void {
                                $output = fopen('php://output', 'w');
                                fputcsv($output, ['Invoice #', 'Gateway', 'Amount', 'Status', 'Date', 'Paid At']);

                                foreach ($records as $invoice) {
                                    fputcsv($output, [
                                        $invoice->number,
                                        $invoice->gateway,
                                        $invoice->formattedAmount(),
                                        $invoice->status->value,
                                        $invoice->date->format('Y-m-d'),
                                        $invoice->paidAt?->format('Y-m-d') ?? '',
                                    ]);
                                }

                                fclose($output);
                            }, 'invoices-' . CarbonImmutable::now()->format('Y-m-d') . '.csv');
                        }),
                ]),
            ])
            ->defaultSort('date', 'desc')
            ->poll(config('filament-cashier.tables.polling_interval', '45s'))
            ->emptyStateHeading(__('filament-cashier::invoices.empty.title'))
            ->emptyStateDescription(__('filament-cashier::invoices.empty.description'))
            ->emptyStateIcon('heroicon-o-document-text');
    }
}
