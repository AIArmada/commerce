<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventSubLocationResource\Schemas;

use AIArmada\Events\Models\EventSubLocation;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class EventSubLocationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Sub-location')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('slug')
                            ->copyable(),
                        TextEntry::make('order_column')
                            ->label('Order')
                            ->placeholder('Not set'),
                        TextEntry::make('occurrences_count')
                            ->label('Occurrences')
                            ->state(fn (EventSubLocation $record): int => $record->occurrences()->count()),
                        TextEntry::make('description')
                            ->columnSpanFull()
                            ->placeholder('No description'),
                    ])
                    ->columns(4),
            ]);
    }
}
