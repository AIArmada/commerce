<?php

declare(strict_types=1);

namespace AIArmada\FilamentStock\Resources\StockReservationResource\Tables;

use AIArmada\FilamentStock\Support\FilamentCartBridge;
use AIArmada\Stock\Models\StockReservation;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class StockReservationsTable
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

                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn (StockReservation $record): string => $record->isValid() ? 'Active' : 'Expired')
                    ->badge()
                    ->color(fn (StockReservation $record): string => $record->isValid() ? 'success' : 'danger')
                    ->icon(fn (StockReservation $record): Heroicon => $record->isValid()
                        ? Heroicon::CheckCircle
                        : Heroicon::XCircle
                    )
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('expires_at', $direction)),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->color('warning'),

                TextColumn::make('cart_id')
                    ->label('Cart')
                    ->searchable()
                    ->limit(12)
                    ->copyable()
                    ->url(function (StockReservation $record): ?string {
                        $bridge = app(FilamentCartBridge::class);

                        return $bridge->getCartUrl($record->cart_id);
                    })
                    ->openUrlInNewTab(),

                TextColumn::make('stockable_type')
                    ->label('Item Type')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('stockable_id')
                    ->label('Item ID')
                    ->searchable()
                    ->limit(8)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->sortable()
                    ->color(fn (StockReservation $record): string => $record->isExpired() ? 'danger' : 'success'),

                TextColumn::make('time_remaining')
                    ->label('Remaining')
                    ->state(function (StockReservation $record): string {
                        if ($record->isExpired()) {
                            return 'Expired';
                        }

                        return $record->expires_at->diffForHumans(now(), [
                            'parts' => 1,
                            'short' => true,
                        ]);
                    })
                    ->badge()
                    ->color(fn (StockReservation $record): string => match (true) {
                        $record->isExpired() => 'danger',
                        $record->expires_at->diffInMinutes(now()) < 5 => 'warning',
                        default => 'success',
                    }),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('active')
                    ->label('Active only')
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->where('expires_at', '>', now())),

                Filter::make('expired')
                    ->label('Expired only')
                    ->query(fn (Builder $query): Builder => $query->where('expires_at', '<=', now())),

                Filter::make('expiring_soon')
                    ->label('Expiring within 5 minutes')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('expires_at', '>', now())
                        ->where('expires_at', '<=', now()->addMinutes(5))
                    ),
            ])
            ->actions([
                ViewAction::make()
                    ->icon(Heroicon::OutlinedEye),

                DeleteAction::make()
                    ->label('Release')
                    ->icon(Heroicon::OutlinedTrash)
                    ->modalHeading('Release Reservation')
                    ->modalDescription('Are you sure you want to release this stock reservation?')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Release selected')
                        ->icon(Heroicon::OutlinedTrash)
                        ->modalHeading('Release Selected Reservations')
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('expires_at', 'asc')
            ->poll(static function (): ?string {
                $interval = config('filament-stock.polling_interval');

                if ($interval === null || $interval === '') {
                    return null;
                }

                return is_numeric($interval) ? $interval.'s' : (string) $interval;
            })
            ->paginated([25, 50, 100])
            ->striped();
    }
}
