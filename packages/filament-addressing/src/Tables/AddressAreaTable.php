<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Tables;

use AIArmada\Addressing\Models\AddressArea;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource;
use AIArmada\FilamentAddressing\Support\AddressingFilterOptions;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AddressAreaTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['names', 'roles', 'parent']))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->url(fn (AddressArea $record): string => AddressAreaResource::getUrl('view', ['record' => $record])),
                TextColumn::make('names.name')
                    ->label('Aliases')
                    ->searchable()
                    ->listWithLineBreaks()
                    ->toggleable(),
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
                TextColumn::make('roles.role')
                    ->label('Roles')
                    ->badge()
                    ->listWithLineBreaks()
                    ->toggleable(),
                TextColumn::make('level')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('code')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->url(fn (AddressArea $record): ?string => $record->parent ? AddressAreaResource::getUrl('view', ['record' => $record->parent]) : null)
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
                    ->options(fn (): array => AddressingFilterOptions::countryOptions())
                    ->searchable(),
                SelectFilter::make('type')
                    ->options(fn (): array => AddressingFilterOptions::areaTypes()),
                SelectFilter::make('level')
                    ->options(fn (): array => AddressingFilterOptions::areaLevels()),
                SelectFilter::make('source')
                    ->options(fn (): array => AddressingFilterOptions::areaSources()),
                SelectFilter::make('role')
                    ->options(fn (): array => AddressingFilterOptions::areaRoles())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $areas, string $role): Builder => $areas->whereHas('roles', fn (Builder $roles): Builder => $roles->where('role', $role)),
                    )),
            ])
            ->defaultSort('country_code')
            ->paginated([10, 25, 50, 100]);
    }
}
