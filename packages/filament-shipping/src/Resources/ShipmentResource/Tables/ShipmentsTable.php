<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ShipmentResource\Tables;

use AIArmada\FilamentShipping\Actions;
use AIArmada\FilamentShipping\Resources\ShipmentResource;
use AIArmada\Shipping\Enums\ShipmentStatus;
use AIArmada\Shipping\Models\Shipment;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class ShipmentsTable
{
    public static function configure(Table $table): Table
    {
        $weightUnit = (string) config('shipping.defaults.weight_unit', 'g');

        return $table
            ->columns([
                TextColumn::make('reference')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('carrier_code')
                    ->label('Carrier')
                    ->badge()
                    ->searchable(),

                TextColumn::make('tracking_number')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Tracking number copied'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ShipmentStatus $state) => $state->getColor())
                    ->icon(fn (ShipmentStatus $state) => $state->getIcon()),

                TextColumn::make('total_weight')
                    ->label('Weight')
                    ->formatStateUsing(fn ($state) => $state === null
                        ? '-'
                        : ($weightUnit === 'kg'
                            ? number_format($state / 1000, 2) . ' kg'
                            : number_format($state) . ' g'))
                    ->sortable(),

                TextColumn::make('shipping_cost')
                    ->label('Cost')
                    ->money(fn (Shipment $record): string => $record->currency, divideBy: 100)
                    ->sortable(),

                TextColumn::make('shipped_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(ShipmentStatus::cases())
                        ->mapWithKeys(fn ($status) => [$status->value => $status->getLabel()])),

                SelectFilter::make('carrier_code')
                    ->label('Carrier')
                    ->options(fn () => ShipmentResource::getCarrierOptions()),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Actions\ShipAction::make(),
                Actions\PrintLabelAction::make(),
                Actions\CancelShipmentAction::make(),
                Actions\SyncTrackingAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    Actions\ShipAction::bulkAction(),
                    Actions\PrintLabelAction::bulkAction(),
                    Actions\CancelShipmentAction::bulkAction(),
                    Actions\SyncTrackingAction::bulkAction(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
