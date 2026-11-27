<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Alerts\EntryPoints\Filament\Resources\SignalAlertLogResource\Tables;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\FilamentSignals\Features\Alerts\EntryPoints\Filament\Resources\SignalAlertLogResource;
use AIArmada\Signals\Features\Alerts\Api\AlertLogAdministration;
use AIArmada\Signals\Features\Alerts\Api\AlertReadState;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertLog;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Throwable;

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
                Tables\Columns\TextColumn::make('tracked_property_name')
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
                    ->authorize('update')
                    ->visible(fn (SignalAlertLog $record): bool => $record->read_at === null)
                    ->action(fn (SignalAlertLog $record) => app(AlertReadState::class)->set([$record->id], true)),
                Action::make('mark_unread')
                    ->label('Mark Unread')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->authorize('update')
                    ->visible(fn (SignalAlertLog $record): bool => $record->read_at !== null)
                    ->action(fn (SignalAlertLog $record) => app(AlertReadState::class)->set([$record->id], false)),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize(fn (): bool => FilamentPermission::hasAbility('signal-alert-log.delete'))
                        ->using(static function (DeleteBulkAction $action, Collection | LazyCollection $records): void {
                            foreach ($records as $record) {
                                try {
                                    if (! SignalAlertLogResource::canDelete($record) || ! app(AlertLogAdministration::class)->delete((string) $record->getKey())) {
                                        $action->reportBulkProcessingFailure();
                                    }
                                } catch (Throwable $exception) {
                                    $action->reportBulkProcessingFailure();
                                    report($exception);
                                }
                            }
                        }),
                ]),
            ]);
    }
}
