<?php

declare(strict_types=1);

namespace AIArmada\FilamentStock\Resources\StockTransactionResource\Tables;

use AIArmada\Stock\Models\StockTransaction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class StockTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(8),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'in' => 'success',
                        'out' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'in' => 'IN',
                        'out' => 'OUT',
                        default => mb_strtoupper($state),
                    })
                    ->icon(fn (string $state): Heroicon => match ($state) {
                        'in' => Heroicon::ArrowUp,
                        'out' => Heroicon::ArrowDown,
                        default => Heroicon::Minus,
                    })
                    ->sortable(),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->alignCenter()
                    ->sortable()
                    ->color(fn (StockTransaction $record): string => match ($record->type) {
                        'in' => 'success',
                        'out' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (int $state, StockTransaction $record): string => $record->type === 'in' ? "+{$state}" : "-{$state}"),

                TextColumn::make('stockable_type')
                    ->label('Item Type')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('stockable.name')
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('stockable_id')
                    ->label('Item ID')
                    ->searchable()
                    ->limit(8)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'sale' => 'success',
                        'restock' => 'info',
                        'adjustment' => 'warning',
                        'return' => 'primary',
                        'damaged' => 'danger',
                        'expired' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => $state !== null ? ucfirst($state) : '-')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('By')
                    ->default('System')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('note')
                    ->label('Note')
                    ->limit(30)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('transaction_date')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'in' => 'Inbound',
                        'out' => 'Outbound',
                    ]),

                SelectFilter::make('reason')
                    ->label('Reason')
                    ->options([
                        'restock' => 'Restock',
                        'sale' => 'Sale',
                        'adjustment' => 'Adjustment',
                        'return' => 'Return',
                        'damaged' => 'Damaged',
                        'expired' => 'Expired',
                        'other' => 'Other',
                    ]),

                Filter::make('today')
                    ->label('Today only')
                    ->query(fn (Builder $query) => $query->whereDate('transaction_date', today())),

                Filter::make('this_week')
                    ->label('This week')
                    ->query(fn (Builder $query) => $query->where('transaction_date', '>=', now()->startOfWeek())),
            ])
            ->actions([
                ViewAction::make()
                    ->icon(Heroicon::OutlinedEye),

                EditAction::make()
                    ->icon(Heroicon::OutlinedPencil),

                DeleteAction::make()
                    ->icon(Heroicon::OutlinedTrash)
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Delete selected')
                        ->icon(Heroicon::OutlinedTrash)
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->poll(static function (): ?string {
                $interval = config('filament-stock.polling_interval');

                if ($interval === null || $interval === '') {
                    return null;
                }

                return is_numeric($interval) ? $interval . 's' : (string) $interval;
            })
            ->paginated([25, 50, 100])
            ->striped();
    }
}
