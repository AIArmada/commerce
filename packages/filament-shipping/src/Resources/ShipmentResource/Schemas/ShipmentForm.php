<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ShipmentResource\Schemas;

use AIArmada\FilamentShipping\Resources\ShipmentResource;
use AIArmada\Shipping\Enums\ShipmentStatus;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ShipmentForm
{
    public static function configure(Schema $schema): Schema
    {
        $currency = (string) config('shipping.defaults.currency', 'MYR');
        $weightUnit = (string) config('shipping.defaults.weight_unit', 'g');

        return $schema
            ->schema([
                Section::make('Shipment Details')
                    ->schema([
                        TextInput::make('reference')
                            ->required()
                            ->maxLength(255),

                        Select::make('carrier_code')
                            ->label('Carrier')
                            ->options(fn () => ShipmentResource::getCarrierOptions())
                            ->required(),

                        TextInput::make('service_code')
                            ->maxLength(50),

                        Select::make('status')
                            ->options(collect(ShipmentStatus::cases())
                                ->mapWithKeys(fn ($status) => [$status->value => $status->getLabel()]))
                            ->required(),

                        TextInput::make('tracking_number')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Origin Address')
                    ->schema([
                        KeyValue::make('origin_address')
                            ->keyLabel('Field')
                            ->valueLabel('Value'),
                    ])
                    ->collapsible(),

                Section::make('Destination Address')
                    ->schema([
                        KeyValue::make('destination_address')
                            ->keyLabel('Field')
                            ->valueLabel('Value'),
                    ])
                    ->collapsible(),

                Section::make('Package Info')
                    ->schema([
                        TextInput::make('package_count')
                            ->numeric()
                            ->default(1)
                            ->minValue(1),

                        TextInput::make('total_weight')
                            ->numeric()
                            ->suffix($weightUnit)
                            ->formatStateUsing(fn ($state) => $state === null
                                ? null
                                : ($weightUnit === 'kg' ? $state / 1000 : $state))
                            ->dehydrateStateUsing(fn ($state) => $state === null
                                ? null
                                : ($weightUnit === 'kg' ? (int) round($state * 1000) : (int) $state)),

                        TextInput::make('declared_value')
                            ->numeric()
                            ->prefix($currency)
                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                            ->dehydrateStateUsing(fn ($state) => $state ? $state * 100 : null),

                        TextInput::make('shipping_cost')
                            ->numeric()
                            ->prefix($currency)
                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                            ->dehydrateStateUsing(fn ($state) => $state ? $state * 100 : null),
                    ])
                    ->columns(2),
            ]);
    }
}
