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

final class EventRegistrationGroupsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrationGroups';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('code')->copyable()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('size')->sortable(),
            ])
            ->headerActions([
                CreateAction::make()->form([
                    TextInput::make('name')->maxLength(255),
                    TextInput::make('code')->required()->unique()->maxLength(255),
                    TextInput::make('size')->numeric(),
                ]),
            ])
            ->actions([
                EditAction::make()->form([
                    TextInput::make('name')->maxLength(255),
                    TextInput::make('code')->required()->unique()->maxLength(255),
                    TextInput::make('size')->numeric(),
                ]),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
