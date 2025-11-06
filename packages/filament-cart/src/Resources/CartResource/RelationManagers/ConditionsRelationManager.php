<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\CartResource\RelationManagers;

use AIArmada\FilamentCart\Actions\ApplyConditionAction;
use AIArmada\FilamentCart\Actions\RemoveConditionAction;
use AIArmada\FilamentCart\Resources\CartConditionResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

final class ConditionsRelationManager extends RelationManager
{
    protected static string $relationship = 'cartConditions';

    protected static ?string $relatedResource = CartConditionResource::class;

    public function table(Table $table): Table
    {
        return CartConditionResource::table($table)
            ->headerActions([
                ApplyConditionAction::make(),
                ApplyConditionAction::makeCustom(),
                RemoveConditionAction::makeClearByType(),
                RemoveConditionAction::makeClearAll(),
            ])
            ->recordActions([
                RemoveConditionAction::make(),
            ]);
    }
}
