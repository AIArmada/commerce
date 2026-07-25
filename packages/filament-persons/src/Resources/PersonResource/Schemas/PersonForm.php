<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\PersonResource\Schemas;

use AIArmada\Persons\Enums\Gender;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PersonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('family_name')
                            ->maxLength(100)
                            ->helperText('Last name / surname for sorting.'),
                        TextInput::make('middle_name')
                            ->maxLength(100),
                        Select::make('gender')
                            ->options(Gender::class),
                        DateTimePicker::make('date_of_birth')
                            ->native(false),
                        TextInput::make('status')
                            ->maxLength(50),
                    ]),
                Section::make('Biography')
                    ->components([
                        Textarea::make('bio')
                            ->rows(5),
                    ]),
            ]);
    }
}
