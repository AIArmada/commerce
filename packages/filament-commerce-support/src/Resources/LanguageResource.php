<?php

declare(strict_types=1);

namespace AIArmada\FilamentCommerceSupport\Resources;

use AIArmada\CommerceSupport\Models\Language;
use AIArmada\FilamentCommerceSupport\Resources\LanguageResource\Pages\ListLanguages;
use AIArmada\FilamentCommerceSupport\Resources\LanguageResource\Pages\ViewLanguage;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LanguageResource extends Resource
{
    protected static ?string $slug = 'languages';

    protected static ?string $model = Language::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-language';

    public static function getNavigationLabel(): string
    {
        return 'Languages';
    }

    public static function getModelLabel(): string
    {
        return 'Language';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Languages';
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-commerce-support.navigation.group');
    }

    public static function getNavigationIcon(): BackedEnum | string | null
    {
        return config('filament-commerce-support.navigation.icons.languages', parent::getNavigationIcon());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-commerce-support.navigation.enabled', true);
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-commerce-support.navigation.sort', 100);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
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
                TextColumn::make('dir')
                    ->badge()
                    ->sortable(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextEntry::make('code'),
                TextEntry::make('name'),
                TextEntry::make('native'),
                TextEntry::make('dir')
                    ->badge(),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('code')
                    ->required()
                    ->maxLength(10),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('native')
                    ->maxLength(255),
                Select::make('dir')
                    ->options([
                        'ltr' => 'LTR',
                        'rtl' => 'RTL',
                    ])
                    ->default('ltr'),
            ])
            ->disabled(self::isReadOnly());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLanguages::route('/'),
            'view' => ViewLanguage::route('/{record}'),
        ];
    }

    public static function isReadOnly(): bool
    {
        return (bool) config('filament-commerce-support.resources.languages.read_only', true);
    }
}
