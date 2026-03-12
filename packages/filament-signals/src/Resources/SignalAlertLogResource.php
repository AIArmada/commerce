<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources;

use AIArmada\FilamentSignals\Resources\SignalAlertLogResource\Pages;
use AIArmada\Signals\Models\SignalAlertLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class SignalAlertLogResource extends Resource
{
    protected static ?string $model = SignalAlertLog::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-bell';

    protected static string | UnitEnum | null $navigationGroup = 'Insights';

    protected static ?int $navigationSort = 34;

    /**
     * @return Builder<SignalAlertLog>
     */
    public static function getEloquentQuery(): Builder
    {
        return SignalAlertLog::query()->forOwner()->with(['alertRule', 'trackedProperty']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
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
                Tables\Columns\IconColumn::make('is_read')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('severity')
                    ->options([
                        'info' => 'Info',
                        'warning' => 'Warning',
                        'critical' => 'Critical',
                    ]),
                Tables\Filters\TernaryFilter::make('is_read'),
            ])
            ->actions([
                Action::make('mark_read')
                    ->label('Mark Read')
                    ->icon('heroicon-o-check')
                    ->visible(fn (SignalAlertLog $record): bool => ! $record->is_read)
                    ->action(function (SignalAlertLog $record): void {
                        $record->markAsRead();
                    }),
                Action::make('mark_unread')
                    ->label('Mark Unread')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn (SignalAlertLog $record): bool => $record->is_read)
                    ->action(function (SignalAlertLog $record): void {
                        $record->markAsUnread();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSignalAlertLogs::route('/'),
        ];
    }
}
