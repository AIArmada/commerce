<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class AreaStateLinksRelationManager extends RelationManager
{
    protected static string $relationship = 'stateLinks';

    protected static ?string $title = 'Canonical State Links';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('state.name')->label('State')->searchable()->sortable(),
                TextColumn::make('state.code')->label('State Code')->searchable(),
                TextColumn::make('hierarchy_type')->label('Hierarchy')->badge(),
                TextColumn::make('metadata')->formatStateUsing(static fn (mixed $state): string => is_array($state)
                    ? (json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '')
                    : (string) ($state ?? ''))
                    ->toggleable(),
            ])
            ->defaultSort('hierarchy_type')
            ->paginated([10, 25, 50]);
    }
}
