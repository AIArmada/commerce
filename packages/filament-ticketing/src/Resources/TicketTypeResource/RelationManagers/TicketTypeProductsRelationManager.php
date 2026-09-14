<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing\Resources\TicketTypeResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class TicketTypeProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'bundleProducts';

    protected static ?string $recordTitleAttribute = 'id';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->with('product'))
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->label('Product'),
                Tables\Columns\TextColumn::make('quantity'),
                Tables\Columns\TextColumn::make('inclusion_mode')->badge(),
            ]);
    }
}
