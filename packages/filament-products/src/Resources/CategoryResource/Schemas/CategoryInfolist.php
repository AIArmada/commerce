<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\CategoryResource\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Category Details')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Name'),
                        TextEntry::make('slug')
                            ->label('Slug')
                            ->copyable(),
                        TextEntry::make('parent.name')
                            ->label('Parent')
                            ->placeholder('Root Category'),
                        TextEntry::make('full_path')
                            ->label('Full Path')
                            ->getStateUsing(fn ($record) => $record->getFullPath()),
                    ])
                    ->columns(2),

                Section::make('Statistics')
                    ->schema([
                        TextEntry::make('products_count')
                            ->label('Direct Products'),
                        TextEntry::make('all_products_count')
                            ->label('All Products (including children)')
                            ->getStateUsing(fn ($record) => $record->getProductCount(true)),
                        TextEntry::make('children_count')
                            ->label('Child Categories'),
                    ])
                    ->columns(3),
            ]);
    }
}
