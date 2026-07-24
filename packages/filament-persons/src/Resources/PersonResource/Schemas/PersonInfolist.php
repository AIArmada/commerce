<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\PersonResource\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PersonInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->columns(2)
                    ->components([
                        TextEntry::make('name'),
                        TextEntry::make('family_name')
                            ->placeholder('-'),
                        TextEntry::make('middle_name')
                            ->placeholder('-'),
                        TextEntry::make('gender'),
                        TextEntry::make('date_of_birth')
                            ->date(),
                        TextEntry::make('status')
                            ->badge()
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
