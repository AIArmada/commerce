<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxZoneResource\RelationManagers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

final class RatesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->label('Rate Name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., Standard SST'),

                Select::make('tax_class')
                    ->label('Tax Class')
                    ->options([
                        'standard' => 'Standard Rate',
                        'reduced' => 'Reduced Rate',
                        'zero' => 'Zero Rate',
                        'exempt' => 'Tax Exempt',
                    ])
                    ->default('standard')
                    ->required(),

                TextInput::make('rate')
                    ->label('Rate (basis points)')
                    ->numeric()
                    ->required()
                    ->helperText('Enter 600 for 6%, 1000 for 10%')
                    ->suffix('bp'),

                Toggle::make('is_compound')
                    ->label('Compound Tax')
                    ->helperText('Applied after other taxes'),

                TextInput::make('priority')
                    ->label('Priority')
                    ->numeric()
                    ->default(0)
                    ->helperText('Order for compound taxes'),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ])
            ->columns(2);
    }
}
