<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\VenueSpaceResource\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VenueSpaceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Space Details')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('slug'),
                        TextEntry::make('code')
                            ->placeholder('-'),
                        TextEntry::make('capacity')
                            ->placeholder('-'),
                        TextEntry::make('space_type')
                            ->badge()
                            ->placeholder('-'),
                        TextEntry::make('level')
                            ->label('Floor / Level')
                            ->placeholder('-'),
                        TextEntry::make('unit_no')
                            ->placeholder('-'),
                        TextEntry::make('block')
                            ->placeholder('-'),
                        TextEntry::make('wing')
                            ->placeholder('-'),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('visibility')
                            ->badge(),
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }
}
