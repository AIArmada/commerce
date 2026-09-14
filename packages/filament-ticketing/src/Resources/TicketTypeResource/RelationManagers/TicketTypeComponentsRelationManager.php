<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing\Resources\TicketTypeResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class TicketTypeComponentsRelationManager extends RelationManager
{
    protected static string $relationship = 'components';

    protected static ?string $recordTitleAttribute = 'componentTicketType.name';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->with('componentTicketType'))
            ->columns([
                Tables\Columns\TextColumn::make('componentTicketType.name')->label('Component'),
                Tables\Columns\TextColumn::make('quantity'),
            ]);
    }
}
