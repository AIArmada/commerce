<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\CartResource\RelationManagers;

use AIArmada\FilamentCart\Resources\CartItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

final class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'cartItems';

    protected static ?string $relatedResource = CartItemResource::class;

    public function table(Table $table): Table
    {
        return CartItemResource::table($table)
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
