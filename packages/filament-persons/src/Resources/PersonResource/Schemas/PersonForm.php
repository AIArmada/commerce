<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\PersonResource\Schemas;

use AIArmada\Persons\Enums\Gender;
use AIArmada\Persons\Enums\PersonStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                        Select::make('status')
                            ->options(PersonStatus::class)
                            ->default(PersonStatus::Active->value)
                            ->required(),
                        self::featuredToggle(),
                    ]),
                Section::make('Biography')
                    ->components([
                        KeyValue::make('bio')
                            ->keyLabel('Locale')
                            ->valueLabel('Text')
                            ->addButtonLabel('Add translation'),
                    ]),
            ]);
    }

    public static function featuredToggle(): Toggle
    {
        return Toggle::make('is_featured')
            ->label(__('Featured'))
            ->helperText(__('Featured records can appear in curated showcases.'))
            ->default(false);
    }
}
