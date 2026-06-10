<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventResource\RelationManagers;

use AIArmada\Events\Enums\EventVisibility;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

final class EventPeopleRelationManager extends RelationManager
{
    protected static string $relationship = 'people';

    protected static ?string $title = 'People';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('display_name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('role_label')
                    ->label('Role')
                    ->maxLength(255),

                TextInput::make('role_key')
                    ->maxLength(255),

                TextInput::make('person_type')
                    ->maxLength(255),

                TextInput::make('person_id')
                    ->maxLength(36),

                Textarea::make('biography')
                    ->rows(4)
                    ->columnSpanFull(),

                TextInput::make('order_column')
                    ->numeric()
                    ->minValue(0),

                Select::make('visibility')
                    ->options(EventVisibility::class)
                    ->default(EventVisibility::Public),

                KeyValue::make('metadata')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('display_name')
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('role_label')
                    ->label('Role')
                    ->placeholder('No role')
                    ->searchable(),

                Tables\Columns\TextColumn::make('person_type')
                    ->label('Person model')
                    ->placeholder('Display-only')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\BadgeColumn::make('visibility')
                    ->label('Visibility')
                    ->colors([
                        'success' => EventVisibility::Public->value,
                        'warning' => EventVisibility::Unlisted->value,
                        'gray' => EventVisibility::Private->value,
                    ])
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('order_column')
                    ->label('Order')
                    ->sortable(),
            ])
            ->defaultSort('order_column')
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
