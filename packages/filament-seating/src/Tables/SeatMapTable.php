<?php

declare(strict_types=1);

namespace AIArmada\FilamentSeating\Tables;

use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\SelectFilter;

/**
 * Shared seat-map table columns and filters, used by the resource and relation managers alike.
 */
final class SeatMapTable
{
    /**
     * @return array<int, Column>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('name')
                ->searchable(),
            TextColumn::make('slug')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('version')
                ->sortable(),
            TextColumn::make('sections_count')
                ->label('Sections')
                ->sortable(),
            TextColumn::make('status')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'active' => 'success',
                    'inactive' => 'gray',
                    'archived' => 'danger',
                    default => 'gray',
                }),
            TextColumn::make('created_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /**
     * @return array<int, BaseFilter>
     */
    public static function filters(): array
    {
        return [
            SelectFilter::make('status')
                ->options([
                    'active' => 'Active',
                    'inactive' => 'Inactive',
                    'archived' => 'Archived',
                ]),
        ];
    }
}
