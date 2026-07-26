<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources;

use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\PostalCode;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource\Pages\CreatePostalCode;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource\Pages\EditPostalCode;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource\Pages\ListPostalCodes;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource\Pages\ViewPostalCode;
use AIArmada\FilamentAddressing\Rules\AddressAreasBelongToCountry;
use AIArmada\FilamentAddressing\Tables\PostalCodeTable;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class PostalCodeResource extends Resource
{
    protected static ?string $slug = 'postal-codes';

    protected static ?string $model = PostalCode::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-envelope';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-addressing.navigation.group');
    }

    public static function getNavigationIcon(): BackedEnum | string | null
    {
        return config('filament-addressing.navigation.icons.postal_codes', parent::getNavigationIcon());
    }

    public static function getNavigationLabel(): string
    {
        return 'Postcodes';
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-addressing.navigation.sort', 80) + 3;
    }

    public static function getModel(): string
    {
        return config('filament-addressing.resources.postal_codes.model', PostalCode::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-addressing.navigation.enabled', true);
    }

    public static function table(Table $table): Table
    {
        return PostalCodeTable::make($table);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Postcode')
                ->schema([
                    Select::make('country_code')
                        ->label('Country')
                        ->options(config('filament-addressing.resources.countries.model', AddressCountry::class)::query()->orderBy('name')->pluck('name', 'iso2')->toArray())
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (callable $set): void {
                            $set('areas', null);
                        }),
                    TextInput::make('code')->label('Postcode')->required()->maxLength(20),
                    Toggle::make('is_active')->default(true),
                ])->columns(3),
            Section::make('Served Areas')
                ->schema([
                    Select::make('areas')
                        ->relationship('areas', 'name', function (Builder $query, callable $get): Builder {
                            $countryCode = $get('country_code');

                            return $query->when(
                                is_string($countryCode) && mb_trim($countryCode) !== '',
                                fn (Builder $areas): Builder => $areas->where('country_code', mb_strtoupper($countryCode)),
                            );
                        })
                        ->multiple()
                        ->searchable()
                        ->rules(fn (callable $get): array => [new AddressAreasBelongToCountry($get('country_code'))]),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Postcode')
                ->schema([
                    TextEntry::make('country_code')->badge(),
                    TextEntry::make('code'),
                    TextEntry::make('is_active')->badge(),
                ])->columns(3),
            Section::make('Served Areas')
                ->schema([TextEntry::make('areas.name')->listWithLineBreaks()]),
        ]);
    }

    public static function getPages(): array
    {
        $pages = [
            'index' => ListPostalCodes::route('/'),
            'view' => ViewPostalCode::route('/{record}'),
        ];

        if (! self::isReadOnly()) {
            $pages['create'] = CreatePostalCode::route('/create');
            $pages['edit'] = EditPostalCode::route('/{record}/edit');
        }

        return $pages;
    }

    public static function isReadOnly(): bool
    {
        return (bool) config('filament-addressing.resources.postal_codes.read_only', false);
    }
}
