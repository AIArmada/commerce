<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SignalAlertLogResource\Tables;

use AIArmada\Signals\Actions\MarkSignalAlertAsRead;
use AIArmada\Signals\Actions\MarkSignalAlertAsUnread;
use AIArmada\Signals\Models\SignalAlertLog;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class SignalAlertLogTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'warning' => 'warning',
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('alertRule.name')
                    ->label('Rule')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('trackedProperty.name')
                    ->label('Property')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('metric_value')
                    ->numeric(decimalPlaces: 2)
                    ->label('Value'),
                Tables\Columns\TextColumn::make('threshold_value')
                    ->numeric(decimalPlaces: 2)
                    ->label('Threshold'),
                Tables\Columns\TextColumn::make('channels_notified')
                    ->badge()
                    ->separator(',')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('context.metric_key')
                    ->label('Context Metric')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('delivery_results')
                    ->label('Delivery')
                    ->formatStateUsing(fn (mixed $state): string => is_array($state) ? implode(', ', array_keys($state)) : '')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('read_at')
                    ->label('Read')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('severity')
                    ->options([
                        'info' => 'Info',
                        'warning' => 'Warning',
                        'critical' => 'Critical',
                    ]),
                TernaryFilter::make('read_at')
                    ->label('Read'),
            ])
            ->actions([
                Action::make('mark_read')
                    ->label('Mark Read')
                    ->icon('heroicon-o-check')
                    ->visible(fn (SignalAlertLog $record): bool => $record->read_at === null)
                    ->action(fn (SignalAlertLog $record) => app(MarkSignalAlertAsRead::class)($record)),
                Action::make('mark_unread')
                    ->label('Mark Unread')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn (SignalAlertLog $record): bool => $record->read_at !== null)
                    ->action(fn (SignalAlertLog $record) => app(MarkSignalAlertAsUnread::class)($record)),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
