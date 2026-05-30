<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateResource\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class PayoutHoldsRelationManager extends RelationManager
{
    protected static string $relationship = 'payoutHolds';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('reason')
                ->required(),

            Textarea::make('notes')
                ->rows(3),

            DateTimePicker::make('expires_at'),

            TextInput::make('placed_by')
                ->label('Placed By')
                ->maxLength(255),

            DateTimePicker::make('released_at'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reason')
            ->columns([
                TextColumn::make('reason')
                    ->searchable(),

                TextColumn::make('notes')
                    ->limit(40)
                    ->placeholder('—'),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('released_at')
                    ->label('Released')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
            ])
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
