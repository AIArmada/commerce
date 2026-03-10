<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Widgets;

use AIArmada\FilamentSignals\Resources\SignalAlertLogResource;
use AIArmada\Signals\Models\SignalAlertLog;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Collection;

final class PendingSignalAlertsWidget extends BaseWidget
{
    protected static ?string $heading = 'Pending Alerts';

    protected static ?string $pollingInterval = '15s';

    protected int | string | array $columnSpan = [
        'md' => 2,
        'xl' => 2,
    ];

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SignalAlertLog::query()->forOwner()
                    ->with(['alertRule', 'trackedProperty'])
                    ->where('is_read', false)
                    ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
                    ->orderByDesc('created_at')
            )
            ->headerActions([
                Actions\Action::make('viewAll')
                    ->label('View All')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(SignalAlertLogResource::getUrl('index')),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'warning' => 'warning',
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('title')
                    ->limit(40)
                    ->tooltip(fn (SignalAlertLog $record): ?string => $record->message),
                Tables\Columns\TextColumn::make('trackedProperty.name')
                    ->label('Property')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->since(),
            ])
            ->actions([
                Actions\Action::make('markRead')
                    ->label('Mark Read')
                    ->icon('heroicon-o-check')
                    ->action(function (SignalAlertLog $record): void {
                        $record->markAsRead();
                    }),
            ])
            ->bulkActions([
                Actions\BulkAction::make('markAllRead')
                    ->label('Mark as Read')
                    ->icon('heroicon-o-check')
                    ->action(function (Collection $records): void {
                        $records->each(static function (SignalAlertLog $record): void {
                            $record->markAsRead();
                        });
                    }),
            ])
            ->emptyStateHeading('No pending alerts')
            ->emptyStateDescription('All Signals alerts are currently acknowledged.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10, 25]);
    }
}
