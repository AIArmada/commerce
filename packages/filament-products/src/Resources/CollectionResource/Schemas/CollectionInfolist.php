<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\CollectionResource\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CollectionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Collection Details')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('slug')
                            ->copyable(),
                        TextEntry::make('type')
                            ->badge(),
                        TextEntry::make('products_count')
                            ->label('Products'),
                    ])
                    ->columns(4),

                Section::make('Scheduling')
                    ->schema([
                        TextEntry::make('published_at')
                            ->label('Publish At')
                            ->dateTime()
                            ->placeholder('Immediate'),
                        TextEntry::make('unpublished_at')
                            ->label('Unpublish At')
                            ->dateTime()
                            ->placeholder('Never'),
                        IconEntry::make('is_currently_published')
                            ->label('Currently Published')
                            ->getStateUsing(fn ($record) => $record->isPublished())
                            ->boolean(),
                    ])
                    ->columns(3),
            ]);
    }
}
