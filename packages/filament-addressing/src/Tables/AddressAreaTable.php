<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Tables;

use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressCountry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AddressAreaTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('native_name')
                    ->label('Native Name')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('country_code')
                    ->label('Country')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('level')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('code')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->toggleable(),
                TextColumn::make('source')
                    ->toggleable(),
                TextColumn::make('source_id')
                    ->label('Source ID')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('synced_at')
                    ->dateTime()
                    ->toggleable()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('country_code')
                    ->label('Country')
                    ->options(
                        config('filament-addressing.resources.countries.model', AddressCountry::class)::query()
                            ->orderBy('name')
                            ->pluck('name', 'iso2')
                            ->toArray(),
                    )
                    ->searchable(),
                SelectFilter::make('type')
                    ->options(fn (): array => self::getTypeOptions()),
                SelectFilter::make('level')
                    ->options(fn (): array => self::getLevelOptions()),
                SelectFilter::make('source')
                    ->options(fn (): array => self::getSourceOptions()),
            ])
            ->defaultSort('country_code')
            ->paginated([10, 25, 50, 100]);
    }

    private static function getTypeOptions(): array
    {
        $areaClass = config('filament-addressing.resources.areas.model', AddressArea::class);

        return $areaClass::query()
            ->distinct()
            ->orderBy('type')
            ->pluck('type', 'type')
            ->toArray();
    }

    private static function getLevelOptions(): array
    {
        $areaClass = config('filament-addressing.resources.areas.model', AddressArea::class);

        return $areaClass::query()
            ->whereNotNull('level')
            ->distinct()
            ->orderBy('level')
            ->pluck('level', 'level')
            ->map(static fn (mixed $level): string => (string) $level)
            ->toArray();
    }

    private static function getSourceOptions(): array
    {
        $areaClass = config('filament-addressing.resources.areas.model', AddressArea::class);

        return $areaClass::query()
            ->distinct()
            ->orderBy('source')
            ->pluck('source', 'source')
            ->toArray();
    }
}
