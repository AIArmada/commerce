<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\VenueResource\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class VenueInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Venue')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('slug')
                            ->copyable(),
                        TextEntry::make('contact_name')
                            ->placeholder('Not set'),
                        TextEntry::make('contact_email')
                            ->copyable()
                            ->placeholder('Not set'),
                        TextEntry::make('contact_phone')
                            ->copyable()
                            ->placeholder('Not set'),
                        TextEntry::make('timezone')
                            ->placeholder('Not set'),
                        TextEntry::make('location_type')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => Str::headline($state ?? 'physical')),
                        TextEntry::make('notes')
                            ->columnSpanFull()
                            ->placeholder('No notes'),
                    ])
                    ->columns(3),

                Section::make('Location')
                    ->schema([
                        TextEntry::make('line1')
                            ->placeholder('Not set'),
                        TextEntry::make('line2')
                            ->placeholder('Not set'),
                        TextEntry::make('city')
                            ->placeholder('Not set'),
                        TextEntry::make('state')
                            ->placeholder('Not set'),
                        TextEntry::make('postcode')
                            ->placeholder('Not set'),
                        TextEntry::make('country'),
                        TextEntry::make('latitude')
                            ->placeholder('Not set'),
                        TextEntry::make('longitude')
                            ->placeholder('Not set'),
                        TextEntry::make('map_url')
                            ->url(fn (?string $state): ?string => $state)
                            ->placeholder('Not set'),
                        TextEntry::make('external_id')
                            ->placeholder('Not set'),
                    ])
                    ->columns(3),
            ]);
    }
}
