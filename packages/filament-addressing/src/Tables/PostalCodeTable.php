<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class PostalCodeTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Postcode')->searchable()->sortable(),
                TextColumn::make('country_code')->label('Country')->badge()->searchable()->sortable(),
                TextColumn::make('areas.name')->label('Served Areas')->listWithLineBreaks()->searchable()->toggleable(),
                IconColumn::make('is_active')->label('Active')->boolean()->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->defaultSort('country_code')
            ->paginated([10, 25, 50, 100]);
    }
}
