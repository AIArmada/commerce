<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\OccurrenceResource\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class EventSeatCategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'seatCategories';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('capacity')->sortable(),
                TextColumn::make('order_column')->sortable(),
            ])
            ->headerActions([
                CreateAction::make()->form([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('capacity')->numeric(),
                    TextInput::make('order_column')->numeric(),
                ]),
            ])
            ->actions([
                EditAction::make()->form([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('capacity')->numeric(),
                    TextInput::make('order_column')->numeric(),
                ]),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
