<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Resources;

use AIArmada\Chip\Models\Purchase;
use AIArmada\FilamentCashier\FilamentCashierPlugin;
use AIArmada\FilamentCashier\Resources\UnifiedInvoiceResource\Pages;
use AIArmada\FilamentCashier\Support\GatewayDetector;
use AIArmada\FilamentCashier\Support\InvoiceStatus;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class UnifiedInvoiceResource extends Resource
{
    public static function getModel(): string
    {
        if (class_exists(Purchase::class)) {
            return Purchase::class;
        }

        $userModel = config('auth.providers.users.model');

        if (is_string($userModel)) {
            return $userModel;
        }

        return \Illuminate\Foundation\Auth\User::class;
    }

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 20;

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-cashier.resources.navigation_sort.invoices', 20);
    }

    public static function getNavigationGroup(): ?string
    {
        return FilamentCashierPlugin::get()->getNavigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-cashier::invoices.title');
    }

    public static function getModelLabel(): string
    {
        return __('filament-cashier::invoices.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-cashier::invoices.plural');
    }

    public static function table(Table $table): Table
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
                    ->getStateUsing(fn (Model $record): string => (string) $record->getAttribute('formatted_amount')),

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
                    ->url(fn (Model $record): ?string => $record->getAttribute('pdf_url'))
                    ->openUrlInNewTab()
                    ->visible(fn (Model $record): bool => $record->getAttribute('pdf_url') !== null),

                Action::make('view_external')
                    ->label(fn (Model $record): string => __('filament-cashier::invoices.actions.view_external', [
                        'gateway' => $record->getAttribute('gateway_config')['label'],
                    ]))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Model $record): string => (string) $record->getAttribute('external_dashboard_url'))
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
                                        $invoice->getAttribute('number'),
                                        $invoice->getAttribute('gateway'),
                                        $invoice->getAttribute('formatted_amount'),
                                        $invoice->getAttribute('status')->value,
                                        $invoice->getAttribute('date')->format('Y-m-d'),
                                        $invoice->getAttribute('paidAt')?->format('Y-m-d') ?? '',
                                    ]);
                                }

                                fclose($output);
                            }, 'invoices-' . now()->format('Y-m-d') . '.csv');
                        }),
                ]),
            ])
            ->defaultSort('date', 'desc')
            ->poll(config('filament-cashier.tables.polling_interval', '45s'))
            ->emptyStateHeading(__('filament-cashier::invoices.empty.title'))
            ->emptyStateDescription(__('filament-cashier::invoices.empty.description'))
            ->emptyStateIcon('heroicon-o-document-text');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
        ];
    }

    public static function resolveRecordRouteBinding(int | string $key, ?Closure $modifyQuery = null): ?Model
    {
        return null;
    }
}
