<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use AIArmada\Cart\Models\AlertLog;
use AIArmada\FilamentCart\Resources\CartResource;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Pending alerts requiring attention widget.
 */
class PendingAlertsWidget extends BaseWidget
{
    protected static ?string $heading = 'Pending Alerts';

    protected static ?string $pollingInterval = '15s';

    protected int | string | array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AlertLog::query()->forOwner()
                    ->where('is_read', false)
                    ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
                    ->orderByDesc('created_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'warning' => 'warning',
                        default => 'info',
                    }),

                Tables\Columns\TextColumn::make('title')
                    ->limit(30)
                    ->tooltip(fn (AlertLog $record) => $record->message),

                Tables\Columns\TextColumn::make('event_type')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->since(),
            ])
            ->actions([
                Actions\Action::make('markRead')
                    ->icon('heroicon-o-check')
                    ->action(fn (AlertLog $record) => $record->markAsRead())
                    ->tooltip('Mark as read'),

                Actions\Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(fn (AlertLog $record) => $record->cart_id
                        ? CartResource::getUrl('view', ['record' => $record->cart_id])
                        : null)
                    ->visible(fn (AlertLog $record) => $record->cart_id !== null)
                    ->tooltip('View cart'),
            ])
            ->bulkActions([
                Actions\BulkAction::make('markAllRead')
                    ->label('Mark as Read')
                    ->icon('heroicon-o-check')
                    ->action(fn ($records) => $records->each->markAsRead()),
            ])
            ->emptyStateHeading('No pending alerts')
            ->emptyStateDescription('All caught up!')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10, 25]);
    }
}
