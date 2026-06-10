<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Resources\PriceListResource\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class PriceListInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Price List Details')
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Name'),

                                TextEntry::make('slug')
                                    ->label('Slug'),

                                TextEntry::make('currency')
                                    ->label('Currency')
                                    ->badge(),

                                TextEntry::make('description')
                                    ->label('Description')
                                    ->columnSpanFull()
                                    ->placeholder('No description'),
                            ])
                            ->columns(2),

                        Section::make('Scheduling')
                            ->schema([
                                TextEntry::make('starts_at')
                                    ->label('Start Date')
                                    ->dateTime('d M Y')
                                    ->placeholder('Not set'),

                                TextEntry::make('ends_at')
                                    ->label('End Date')
                                    ->dateTime('d M Y')
                                    ->placeholder('Not set'),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Settings')
                            ->schema([
                                TextEntry::make('deactivated_at')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => $state === null ? 'Active' : 'Deactivated')
                                    ->color(fn ($state) => $state === null ? 'success' : 'danger'),

                                IconEntry::make('is_default')
                                    ->label('Default Price List')
                                    ->boolean(),

                                TextEntry::make('priority')
                                    ->label('Priority')
                                    ->numeric(),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }
}
