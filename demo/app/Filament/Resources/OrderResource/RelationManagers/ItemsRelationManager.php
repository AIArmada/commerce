<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Actions\BulkAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Select;
use Filament\Schemas\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

final class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('quantity')
                    ->numeric()
                    ->required()
                    ->default(1)
                    ->minValue(1),

                TextInput::make('unit_price')
                    ->numeric()
                    ->prefix('RM')
                    ->required(),

                TextInput::make('total_price')
                    ->numeric()
                    ->prefix('RM')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable(),

                TextColumn::make('name')
                    ->searchable()
                    ->description(fn ($record) => $record->sku),

                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('unit_price')
                    ->formatStateUsing(fn (?int $state): ?string => $state !== null ? 'MYR ' . number_format($state / 100, 2) : null)
                    ->sortable(),

                TextColumn::make('total_price')
                    ->formatStateUsing(fn (?int $state): ?string => $state !== null ? 'MYR ' . number_format($state / 100, 2) : null)
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkAction::make('delete')
                    ->label('Delete Selected')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each->delete())
                    ->deselectRecordsAfterCompletion(),
            ]);
    }
}
