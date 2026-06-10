<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventSeriesResource\Schemas;

use AIArmada\Events\Models\EventSeries;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class EventSeriesInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Series')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('slug')
                            ->copyable(),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'archived' => 'gray',
                                default => 'gray',
                            }),
                        TextEntry::make('events_count')
                            ->label('Events')
                            ->state(fn (EventSeries $record): int => $record->events()->count()),
                        TextEntry::make('description')
                            ->columnSpanFull()
                            ->placeholder('No description'),
                    ])
                    ->columns(4),
            ]);
    }
}
