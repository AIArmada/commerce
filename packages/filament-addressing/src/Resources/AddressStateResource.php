<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources;

use AIArmada\Addressing\Models\State;
use AIArmada\FilamentAddressing\Resources\AddressStateResource\Pages\EditAddressState;
use AIArmada\FilamentAddressing\Resources\AddressStateResource\Pages\ListAddressStates;
use AIArmada\FilamentAddressing\Resources\AddressStateResource\Pages\ViewAddressState;
use AIArmada\FilamentAddressing\Tables\AddressStateTable;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

final class AddressStateResource extends Resource
{
    protected static ?string $model = State::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-map';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-addressing.navigation.group');
    }

    public static function getNavigationIcon(): BackedEnum | string | null
    {
        return config('filament-addressing.navigation.icons.states', parent::getNavigationIcon());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-addressing.navigation.enabled', true);
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-addressing.navigation.sort', 80);
    }

    public static function getModel(): string
    {
        return config('filament-addressing.resources.states.model', State::class);
    }

    public static function table(Table $table): Table
    {
        return AddressStateTable::make($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Identity')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('code')->label('Code'),
                        TextEntry::make('type')->badge(),
                        TextEntry::make('label'),
                        TextEntry::make('latitude')
                            ->numeric(5),
                        TextEntry::make('longitude')
                            ->numeric(5),
                    ])->columns(2),
                Section::make('Country')
                    ->schema([
                        TextEntry::make('country.name'),
                        TextEntry::make('country.iso2')->label('ISO2'),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        $pages = [
            'index' => ListAddressStates::route('/'),
            'view' => ViewAddressState::route('/{record}'),
        ];

        if (! self::isReadOnly()) {
            $pages['edit'] = EditAddressState::route('/{record}/edit');
        }

        return $pages;
    }

    public static function isReadOnly(): bool
    {
        return (bool) config('filament-addressing.resources.states.read_only', false);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Identity')
                    ->schema([
                        TextInput::make('name')->required(),
                        TextInput::make('code')->label('Code'),
                        TextInput::make('type'),
                        TextInput::make('label'),
                        TextInput::make('latitude')
                            ->numeric()
                            ->step(0.0000001),
                        TextInput::make('longitude')
                            ->numeric()
                            ->step(0.0000001),
                    ])->columns(2),
                Section::make('Country')
                    ->schema([
                        Select::make('country_id')
                            ->relationship('country', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])->columns(2),
            ]);
    }
}
