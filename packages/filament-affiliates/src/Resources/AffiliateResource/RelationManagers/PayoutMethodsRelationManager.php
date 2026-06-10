<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateResource\RelationManagers;

use AIArmada\Affiliates\Enums\PayoutMethodType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class PayoutMethodsRelationManager extends RelationManager
{
    protected static string $relationship = 'payoutMethods';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('type')
                ->options(PayoutMethodType::class)
                ->required(),

            KeyValue::make('details')
                ->required(),

            DateTimePicker::make('verified_at'),

            Toggle::make('is_default')
                ->default(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                TextColumn::make('type')
                    ->badge(),

                TextColumn::make('label')
                    ->label('Label')
                    ->placeholder('—'),

                IconColumn::make('verified_at')
                    ->label('Verified')
                    ->boolean(),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
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
