<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Tables;

use AIArmada\Addressing\Models\PostalCode;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class PostalCodeTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['areas']))
            ->columns([
                TextColumn::make('code')
                    ->label('Postcode')
                    ->url(static fn (PostalCode $record): string => PostalCodeResource::getUrl('view', ['record' => $record]))
                    ->searchable()
                    ->sortable(),
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
