<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventResource\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class EventOrganizersRelationManager extends RelationManager
{
    protected static string $relationship = 'organizers';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')->searchable()->sortable(),
                TextColumn::make('order_column')->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
