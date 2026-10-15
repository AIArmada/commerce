<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\RelationManagers;

use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class OutgoingAreaRelationshipsRelationManager extends RelationManager
{
    protected static string $relationship = 'outgoingAreaRelationships';

    protected static ?string $title = 'Child Relationships';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('child.name')
            ->columns([
                TextColumn::make('child.name')->label('Child area')->searchable()->sortable()
                    ->url(fn (AddressAreaRelationship $record): ?string => $record->child && AddressAreaResource::canView($record->child)
                        ? AddressAreaResource::getUrl('view', ['record' => $record->child])
                        : null),
                TextColumn::make('relationship_type')->label('Relationship')->badge(),
                TextColumn::make('hierarchy_type')->label('Hierarchy')->badge(),
                TextColumn::make('source')->label('Source')->badge(),
                TextColumn::make('valid_from')->label('Valid from')->date(),
                TextColumn::make('valid_until')->label('Valid until')->date(),
            ])
            ->defaultSort('child.name')
            ->paginated([10, 25, 50]);
    }
}
