<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\RelationManagers;

use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class IncomingAreaRelationshipsRelationManager extends RelationManager
{
    protected static string $relationship = 'incomingAreaRelationships';

    protected static ?string $title = 'Parent Relationships';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('parent.name')
            ->columns([
                TextColumn::make('parent.name')->label('Parent area')->searchable()->sortable()
                    ->url(fn (AddressAreaRelationship $record): ?string => $record->parent && AddressAreaResource::canView($record->parent)
                        ? AddressAreaResource::getUrl('view', ['record' => $record->parent])
                        : null),
                TextColumn::make('relationship_type')->label('Relationship')->badge(),
                TextColumn::make('hierarchy_type')->label('Hierarchy')->badge(),
                TextColumn::make('source')->label('Source')->badge(),
                TextColumn::make('valid_from')->label('Valid from')->date(),
                TextColumn::make('valid_until')->label('Valid until')->date(),
            ])
            ->defaultSort('parent.name')
            ->paginated([10, 25, 50]);
    }
}
