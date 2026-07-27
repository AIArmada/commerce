<?php

declare(strict_types=1);

namespace AIArmada\FilamentCommerceSupport\Resources;

use AIArmada\CommerceSupport\Models\Currency;
use AIArmada\FilamentCommerceSupport\Resources\CurrencyResource\Pages\ListCurrencies;
use AIArmada\FilamentCommerceSupport\Resources\CurrencyResource\Pages\ViewCurrency;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CurrencyResource extends Resource
{
    protected static ?string $slug = 'currencies';

    protected static ?string $model = Currency::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-currency-dollar';

    public static function getNavigationLabel(): string
    {
        return 'Currencies';
    }

    public static function getModelLabel(): string
    {
        return 'Currency';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Currencies';
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-commerce-support.navigation.group');
    }

    public static function getNavigationIcon(): BackedEnum | string | null
    {
        return config('filament-commerce-support.navigation.icons.currencies', parent::getNavigationIcon());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-commerce-support.navigation.enabled', true);
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-commerce-support.navigation.sort', 100);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('symbol')
                    ->sortable(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextEntry::make('code'),
                TextEntry::make('name'),
                TextEntry::make('symbol'),
                TextEntry::make('symbol_native'),
                TextEntry::make('precision'),
                IconEntry::make('symbol_first')->boolean(),
                TextEntry::make('decimal_mark'),
                TextEntry::make('thousands_separator'),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('code')
                    ->required()
                    ->maxLength(3),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('symbol'),
                TextInput::make('symbol_native'),
                TextInput::make('precision')
                    ->numeric()
                    ->default(2),
                Toggle::make('symbol_first'),
                TextInput::make('decimal_mark')
                    ->maxLength(1),
                TextInput::make('thousands_separator')
                    ->maxLength(1),
            ])
            ->disabled(self::isReadOnly());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCurrencies::route('/'),
            'view' => ViewCurrency::route('/{record}'),
        ];
    }

    public static function isReadOnly(): bool
    {
        return (bool) config('filament-commerce-support.resources.currencies.read_only', true);
    }
}
