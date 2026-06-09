<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ShippingRateResource\Tables;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\Shipping\Models\ShippingRate;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class ShippingRatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('zone.name')
                    ->label('Zone')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('method_code')
                    ->badge()
                    ->searchable(),

                TextColumn::make('carrier_code')
                    ->badge()
                    ->color('info')
                    ->placeholder('All'),

                TextColumn::make('calculation_type')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'flat' => 'success',
                        'per_kg' => 'info',
                        'per_item' => 'warning',
                        'percentage' => 'primary',
                        'table' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('base_rate')
                    ->formatStateUsing(fn (ShippingRate $record): string => $record->formatted_base_rate)
                    ->sortable(),

                TextColumn::make('delivery_estimate')
                    ->label('Delivery')
                    ->getStateUsing(fn (ShippingRate $record) => $record->getDeliveryEstimate())
                    ->placeholder('-'),

                IconColumn::make('active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('zone_id')
                    ->label('Zone')
                    ->relationship('zone', 'name', function (Builder $query): Builder {
                        if (! (bool) config('shipping.features.owner.enabled', false)) {
                            return $query;
                        }

                        $owner = OwnerContext::resolve();

                        if ($owner === null) {
                            return $query->whereRaw('0 = 1');
                        }

                        return OwnerQuery::applyToEloquentBuilder(
                            $query->withoutGlobalScope(OwnerScope::class),
                            $owner,
                            (bool) config('shipping.features.owner.include_global', false),
                        );
                    }),

                SelectFilter::make('calculation_type')
                    ->options([
                        'flat' => 'Flat Rate',
                        'per_kg' => 'Per Kilogram',
                        'per_item' => 'Per Item',
                        'percentage' => 'Percentage',
                        'table' => 'Table Based',
                    ]),

                SelectFilter::make('carrier_code')
                    ->options([
                        'jnt' => 'J&T Express',
                        'flat_rate' => 'Flat Rate',
                        'manual' => 'Manual',
                    ]),

                TernaryFilter::make('active'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('zone.name');
    }
}
