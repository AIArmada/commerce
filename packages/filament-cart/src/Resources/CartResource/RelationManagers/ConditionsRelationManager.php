<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\CartResource\RelationManagers;

use AIArmada\FilamentCart\Actions\ApplyConditionAction;
use AIArmada\FilamentCart\Actions\RemoveConditionAction;
use AIArmada\FilamentCart\Resources\CartResource\Tables\CartSnapshotConditionsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

final class ConditionsRelationManager extends RelationManager
{
    protected static string $relationship = 'cartConditions';

    public function table(Table $table): Table
    {
        return CartSnapshotConditionsTable::configure($table)
            ->headerActions([
                ApplyConditionAction::make(),
                ApplyConditionAction::makeCustom(),
                RemoveConditionAction::makeClearByType(),
                RemoveConditionAction::makeClearAll(),
            ]);
    }
}
