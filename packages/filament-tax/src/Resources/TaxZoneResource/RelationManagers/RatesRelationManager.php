<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxZoneResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RatesRelationManager extends RelationManager
{
    protected static string $relationship = 'rates';

    protected static ?string $title = 'Tax Rates';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Rate Name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., Standard SST'),

                Forms\Components\Select::make('tax_class')
                    ->label('Tax Class')
                    ->options([
                        'standard' => 'Standard Rate',
                        'reduced' => 'Reduced Rate',
                        'zero' => 'Zero Rate',
                        'exempt' => 'Tax Exempt',
                    ])
                    ->default('standard')
                    ->required(),

                Forms\Components\TextInput::make('rate')
                    ->label('Rate (basis points)')
                    ->numeric()
                    ->required()
                    ->helperText('Enter 600 for 6%, 1000 for 10%')
                    ->suffix('bp'),

                Forms\Components\Toggle::make('is_compound')
                    ->label('Compound Tax')
                    ->helperText('Applied after other taxes'),

                Forms\Components\TextInput::make('priority')
                    ->label('Priority')
                    ->numeric()
                    ->default(0)
                    ->helperText('Order for compound taxes'),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Rate Name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('tax_class')
                    ->label('Class')
                    ->badge(),

                Tables\Columns\TextColumn::make('rate')
                    ->label('Rate')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2) . '%')
                    ->alignEnd(),

                Tables\Columns\IconColumn::make('is_compound')
                    ->label('Compound')
                    ->boolean(),

                Tables\Columns\TextColumn::make('priority')
                    ->label('Priority')
                    ->numeric(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
