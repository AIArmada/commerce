<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Schemas;

use AIArmada\Addressing\Actions\BuildAddressNavigationLinksAction;
use AIArmada\Addressing\Models\Address;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AddressInfolistSchema
{
    public static function make(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Address')
                    ->schema([
                        TextEntry::make('label'),
                        TextEntry::make('formatted_address'),
                        TextEntry::make('line1'),
                        TextEntry::make('line2'),
                        TextEntry::make('line3'),
                    ])->columns(2),
                Section::make('Location')
                    ->schema([
                        TextEntry::make('city'),
                        TextEntry::make('state'),
                        TextEntry::make('postcode'),
                        TextEntry::make('country'),
                        TextEntry::make('country_code')->label('Country Code'),
                    ])->columns(3),
                Section::make('Canonical Location')
                    ->schema([
                        TextEntry::make('canonical_country')
                            ->label('Canonical Country')
                            ->state(fn (Address $record): ?string => $record->country()->first()?->name),
                        TextEntry::make('country_id')->label('Country ID'),
                        TextEntry::make('canonical_state')
                            ->label('Canonical State')
                            ->state(fn (Address $record): ?string => $record->state()->first()?->name),
                        TextEntry::make('state_id')->label('State ID'),
                        TextEntry::make('canonical_city')
                            ->label('Canonical City')
                            ->state(fn (Address $record): ?string => $record->city()->first()?->name),
                        TextEntry::make('city_id')->label('City ID'),
                    ])->columns(3),
                Section::make('Area Assignments')
                    ->schema([
                        RepeatableEntry::make('areaAssignments')
                            ->label('Assigned areas')
                            ->state(fn (Address $record) => $record->areaAssignments()
                                ->with('area')
                                ->orderBy('role')
                                ->orderBy('address_area_id')
                                ->get())
                            ->schema([
                                TextEntry::make('role')->label('Role')->badge(),
                                TextEntry::make('area.name')->label('Area'),
                                TextEntry::make('area.type')->label('Area Type')->badge(),
                                TextEntry::make('is_primary')->label('Primary')->badge()
                                    ->formatStateUsing(static fn (mixed $state): string => $state ? 'Primary' : 'Secondary'),
                            ])->columns(4),
                    ]),
                Section::make('Coordinates')
                    ->schema([
                        TextEntry::make('latitude'),
                        TextEntry::make('longitude'),
                    ])->columns(2),
                Section::make('Maps')
                    ->schema([
                        TextEntry::make('google_maps_url')
                            ->label('Google Maps')
                            ->state(fn (Address $record): ?string => app(BuildAddressNavigationLinksAction::class)->execute($record)['google_maps_url'])
                            ->url(fn (Address $record): ?string => app(BuildAddressNavigationLinksAction::class)->execute($record)['google_maps_url'])
                            ->openUrlInNewTab(),
                        TextEntry::make('waze_url')
                            ->label('Waze')
                            ->state(fn (Address $record): ?string => app(BuildAddressNavigationLinksAction::class)->execute($record)['waze_url'])
                            ->url(fn (Address $record): ?string => app(BuildAddressNavigationLinksAction::class)->execute($record)['waze_url'])
                            ->openUrlInNewTab(),
                        TextEntry::make('google_place_id'),
                        TextEntry::make('google_feature_id'),
                        TextEntry::make('google_cid'),
                        TextEntry::make('google_entity_id'),
                    ])->columns(2),
            ]);
    }
}
