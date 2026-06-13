<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Schemas;

use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\AddressAreaHierarchy;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AddressAreaFormSchema
{
    public static function form(Schema $schema): Schema
    {
        $record = $schema->getRecord();
        $currentAreaId = $record instanceof AddressArea ? (string) $record->getKey() : null;

        return $schema
            ->schema([
                Section::make('Identity')
                    ->schema([
                        Select::make('country_id')
                            ->label('Country')
                            ->options(
                                AddressCountry::query()
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (AddressCountry $c): array => [$c->id => "{$c->iso2} — {$c->name}"])
                                    ->toArray(),
                            )
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('parent_id', null)),
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('native_name')
                            ->label('Native Name')
                            ->maxLength(255),
                        TextInput::make('code')
                            ->label('Code')
                            ->maxLength(100),
                    ])->columns(2),
                Section::make('Classification')
                    ->schema([
                        TextInput::make('type')
                            ->label('Type')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g. state, province, district, city'),
                        TextInput::make('level')
                            ->label('Level')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10),
                        Select::make('parent_id')
                            ->label('Parent')
                            ->options(
                                fn (callable $get): array => AddressAreaHierarchy::parentOptions(
                                    $get('country_id'),
                                    $currentAreaId,
                                ),
                            )
                            ->searchable()
                            ->placeholder('None (top-level)'),
                    ])->columns(2),
                Section::make('Coordinates')
                    ->schema([
                        TextInput::make('latitude')
                            ->label('Latitude')
                            ->numeric()
                            ->step(0.000001),
                        TextInput::make('longitude')
                            ->label('Longitude')
                            ->numeric()
                            ->step(0.000001),
                    ])->columns(2),
                Section::make('Source')
                    ->schema([
                        TextInput::make('source')
                            ->label('Source')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('source_id')
                            ->label('Source ID')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('parent_source_id')
                            ->label('Parent Source ID')
                            ->maxLength(255),
                    ])->columns(2)
                    ->visible(fn (): bool => (bool) config('filament-addressing.features.show_source_payload')),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Identity')
                    ->schema([
                        TextEntry::make('country.name')->label('Country'),
                        TextEntry::make('type')->badge(),
                        TextEntry::make('level'),
                        TextEntry::make('name'),
                        TextEntry::make('native_name'),
                        TextEntry::make('code'),
                        TextEntry::make('slug'),
                    ])->columns(3),
                Section::make('Hierarchy')
                    ->schema([
                        TextEntry::make('parent.name')->label('Parent'),
                        TextEntry::make('parent_source_id')->label('Parent Source ID'),
                    ])->columns(2),
                Section::make('Coordinates')
                    ->schema([
                        TextEntry::make('latitude'),
                        TextEntry::make('longitude'),
                    ])->columns(2),
                Section::make('Source')
                    ->schema([
                        TextEntry::make('source'),
                        TextEntry::make('source_id'),
                        TextEntry::make('synced_at')->dateTime(),
                    ])->columns(2),
            ]);
    }
}
