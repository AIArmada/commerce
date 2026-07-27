<?php

declare(strict_types=1);

namespace AIArmada\FilamentCommerceSupport\Resources;

use AIArmada\CommerceSupport\Models\Timezone;
use AIArmada\FilamentCommerceSupport\Resources\TimezoneResource\Pages\ListTimezones;
use AIArmada\FilamentCommerceSupport\Resources\TimezoneResource\Pages\ViewTimezone;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TimezoneResource extends Resource
{
    protected static ?string $slug = 'timezones';

    protected static ?string $model = Timezone::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-globe-alt';

    public static function getModelLabel(): string
    {
        return 'Timezone';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Timezones';
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-commerce-support.navigation.group');
    }

    public static function getNavigationIcon(): BackedEnum | string | null
    {
        return config('filament-commerce-support.navigation.icons.timezones', parent::getNavigationIcon());
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-commerce-support.navigation.sort');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->paginated([10, 25, 50, 100]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextEntry::make('name'),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name'),
            ])
            ->disabled(self::isReadOnly());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTimezones::route('/'),
            'view' => ViewTimezone::route('/{record}'),
        ];
    }

    public static function isReadOnly(): bool
    {
        return (bool) config('filament-commerce-support.resources.timezones.read_only', true);
    }
}
