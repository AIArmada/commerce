<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventSeriesResource\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class EventSeriesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Series')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state): mixed => $set('slug', Str::slug($state ?? ''))),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),

                        Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'archived' => 'Archived',
                            ])
                            ->default('active')
                            ->required(),

                        KeyValue::make('metadata')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
