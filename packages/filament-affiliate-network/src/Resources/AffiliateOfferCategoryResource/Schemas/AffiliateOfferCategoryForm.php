<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferCategoryResource\Schemas;

use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\FilamentAffiliateNetwork\Support\AffiliateNetworkOptionsProvider;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class AffiliateOfferCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Category Details')
                    ->schema([
                        Select::make('parent_id')
                            ->label('Parent Category')
                            ->options(fn (?AffiliateOfferCategory $record): array => AffiliateNetworkOptionsProvider::parentCategoryOptions(
                                excludeId: $record !== null ? (string) $record->id : null,
                            ))
                            ->searchable()
                            ->nullable(),

                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        TextInput::make('icon')
                            ->maxLength(100)
                            ->placeholder('heroicon-o-tag'),

                        Textarea::make('description')
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),

                        Toggle::make('is_active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}
