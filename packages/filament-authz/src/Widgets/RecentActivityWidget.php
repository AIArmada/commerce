<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Widgets;

use AIArmada\FilamentAuthz\Models\PermissionAuditLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentActivityWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Permission Activity';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PermissionAuditLog::query()
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Event')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label()),
                Tables\Columns\TextColumn::make('severity')
                    ->label('Severity')
                    ->badge()
                    ->color(fn ($state) => match ($state->value) {
                        'low' => 'gray',
                        'medium' => 'warning',
                        'high' => 'danger',
                        'critical' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50),
                Tables\Columns\TextColumn::make('actor_id')
                    ->label('Actor')
                    ->default('System'),
            ])
            ->paginated(false);
    }
}
