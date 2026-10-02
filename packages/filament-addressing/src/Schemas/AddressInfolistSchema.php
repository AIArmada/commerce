<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Schemas;

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
                    ])->columns(2),
                Section::make('Location')
                    ->schema([
                        TextEntry::make('city'),
                        TextEntry::make('state'),
                        TextEntry::make('postcode'),
                        TextEntry::make('country'),
                        TextEntry::make('country_code')->label('Country Code'),
                    ])->columns(3),
                Section::make('Coordinates / Validation')
                    ->schema([
                        TextEntry::make('latitude'),
                        TextEntry::make('longitude'),
                        TextEntry::make('validation_status')->badge(),
                        TextEntry::make('validated_at')->dateTime(),
                    ])->columns(2),
                Section::make('Maps')
                    ->schema([
                        TextEntry::make('google_maps_url'),
                        TextEntry::make('waze_url'),
                        TextEntry::make('google_place_id'),
                        TextEntry::make('google_feature_id'),
                        TextEntry::make('google_cid'),
                        TextEntry::make('google_entity_id'),
                    ])->columns(2),
            ]);
    }
}
