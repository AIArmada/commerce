<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\RelationManagers;

use AIArmada\Addressing\Models\AddressArea;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ChildAreasRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    protected static ?string $relatedResource = AddressAreaResource::class;

    protected static ?string $title = 'Child Areas';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->url(fn (AddressArea $record): string => AddressAreaResource::getUrl('view', ['record' => $record])),
                TextColumn::make('type')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('level')
                    ->sortable(),
                TextColumn::make('code')
                    ->searchable()
                    ->toggleable(),
            ])
            ->defaultSort('name');
    }
}
