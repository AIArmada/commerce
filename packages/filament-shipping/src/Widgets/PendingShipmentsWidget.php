<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Widgets;

use AIArmada\FilamentShipping\Resources\ShipmentResource;
use AIArmada\FilamentShipping\Support\OwnerScopedQuery;
use AIArmada\Shipping\Features\Shipments\Models\Shipment;
use AIArmada\Shipping\Features\Shipments\States\Pending;
use AIArmada\Shipping\Features\Shipments\States\ShipmentStatus;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PendingShipmentsWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function getHeading(): string
    {
        return 'Pending Shipments';
    }

    public function table(Table $table): Table
    {
        $weightUnit = (string) config('shipping.defaults.weight_unit', 'g');

        $query = OwnerScopedQuery::apply(Shipment::query());

        return $table
            ->query(
                $query
                    ->where('status', ShipmentStatus::normalize(Pending::class))
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->searchable(),

                Tables\Columns\TextColumn::make('carrier_code')
                    ->label('Carrier')
                    ->badge(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (ShipmentStatus $state): string => $state->color()),

                Tables\Columns\TextColumn::make('total_weight')
                    ->label('Weight')
                    ->formatStateUsing(fn ($state) => $state === null
                        ? '-'
                        : ($weightUnit === 'kg'
                            ? number_format($state / 1000, 2) . ' kg'
                            : number_format($state) . ' g')),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Action::make('view')
                    ->url(fn (Shipment $record) => ShipmentResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated(false);
    }
}
