<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SavedSignalReportResource\Tables;

use AIArmada\Signals\Services\SavedSignalReportDefinition;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class SavedSignalReportTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('report_type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('trackedProperty.name')
                    ->label('Property')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('segment.name')
                    ->label('Segment')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_shared')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_shared'),
                TernaryFilter::make('is_active'),
                SelectFilter::make('report_type')
                    ->options(SavedSignalReportDefinition::reportTypeOptions()),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
