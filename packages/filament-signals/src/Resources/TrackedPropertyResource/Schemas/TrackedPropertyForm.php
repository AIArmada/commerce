<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\TrackedPropertyResource\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class TrackedPropertyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Property')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', $state ? Str::slug($state) : '')),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->alphaDash()
                        ->unique(ignoreRecord: true),

                    Forms\Components\TextInput::make('domain')
                        ->maxLength(255),

                    Forms\Components\Select::make('type')
                        ->options([
                            'website' => 'Website',
                            'storefront' => 'Storefront',
                            'app' => 'App',
                        ])
                        ->default('website')
                        ->required(),

                    Forms\Components\TextInput::make('timezone')
                        ->default(config('signals.defaults.timezone', 'UTC'))
                        ->required()
                        ->maxLength(100),

                    Forms\Components\TextInput::make('currency')
                        ->default(config('signals.defaults.currency', 'MYR'))
                        ->required(fn (): bool => (bool) config('signals.features.monetary.enabled', true))
                        ->length(3)
                        ->visible(fn (): bool => (bool) config('signals.features.monetary.enabled', true)),

                    Forms\Components\TextInput::make('write_key')
                        ->required()
                        ->default(Str::random(40))
                        ->unique(ignoreRecord: true)
                        ->maxLength(100)
                        ->helperText('Use this key from your tracker or ingestion client.'),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }
}
