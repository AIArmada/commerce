<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SignalAlertRuleResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class SignalAlertRuleTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('trackedProperty.name')
                    ->label('Property')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('metric_key')
                    ->badge(),
                Tables\Columns\TextColumn::make('event_filters.event_names')
                    ->label('Events')
                    ->badge()
                    ->separator(',')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('channels')
                    ->badge()
                    ->separator(',')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('threshold')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('timeframe_minutes')
                    ->label('Window')
                    ->suffix(' min'),
                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'warning' => 'warning',
                        default => 'info',
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('last_triggered_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('logs_count')
                    ->counts('logs')
                    ->label('Alerts'),
            ])
            ->defaultSort('priority', 'desc')
            ->filters([
                SelectFilter::make('severity')
                    ->options([
                        'info' => 'Info',
                        'warning' => 'Warning',
                        'critical' => 'Critical',
                    ]),
                TernaryFilter::make('is_active'),
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
