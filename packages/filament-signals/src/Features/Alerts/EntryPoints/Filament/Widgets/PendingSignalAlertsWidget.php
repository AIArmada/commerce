<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Alerts\EntryPoints\Filament\Widgets;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\FilamentSignals\Features\Alerts\EntryPoints\Filament\Resources\SignalAlertLogResource;
use AIArmada\Signals\Features\Alerts\Api\AlertReadState;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertLog;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class PendingSignalAlertsWidget extends BaseWidget
{
    protected static ?string $heading = 'Pending Alerts';

    protected static ?string $pollingInterval = '15s';

    protected int | string | array $columnSpan = [
        'md' => 2,
        'xl' => 2,
    ];

    public static function canView(): bool
    {
        return SignalAlertLogResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getPendingAlertsQuery())
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
                Tables\Columns\TextColumn::make('tracked_property_name')
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
                    ->authorize('update')
                    ->action(fn (SignalAlertLog $record) => app(AlertReadState::class)->set([$record->id], true)),
            ])
            ->bulkActions([
                Actions\BulkAction::make('markAllRead')
                    ->label('Mark as Read')
                    ->icon('heroicon-o-check')
                    ->authorize(fn (): bool => FilamentPermission::hasAbility('signal-alert-log.update'))
                    ->action(fn (Collection $records) => app(AlertReadState::class)->set($records->modelKeys(), true)),
            ])
            ->emptyStateHeading('No pending alerts')
            ->emptyStateDescription('All Signals alerts are currently acknowledged.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10, 25]);
    }

    /**
     * @return Builder<SignalAlertLog>
     */
    protected function getPendingAlertsQuery(): Builder
    {
        return SignalAlertLogResource::getEloquentQuery()
            ->whereNull('read_at')
            ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at');
    }
}
