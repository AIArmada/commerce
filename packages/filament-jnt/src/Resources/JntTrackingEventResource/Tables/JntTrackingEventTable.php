<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Resources\JntTrackingEventResource\Tables;

use AIArmada\Jnt\Models\JntTrackingEvent;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class JntTrackingEventTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                TextColumn::make('tracking_number')
                    ->label('Tracking #')
                    ->icon('heroicon-o-truck')
                    ->iconColor('primary')
                    ->copyable()
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('order_reference')
                    ->label('Order Ref')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('scan_type_name')
                    ->label('Status')
                    ->badge()
                    ->color(fn (JntTrackingEvent $record): string => self::getStatusColor($record->scan_type_code))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('scan_time')
                    ->label('Scan Time')
                    ->dateTime(config('filament-jnt.tables.datetime_format', 'Y-m-d H:i:s'))
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->searchable()
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('scan_network_name')
                    ->label('Location')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('scan_network_city')
                    ->label('City')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                IconColumn::make('problem_type')
                    ->label('Problem')
                    ->icon(fn (?string $state): string => $state ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                    ->color(fn (?string $state): string => $state ? 'danger' : 'success')
                    ->sortable(),
                TextColumn::make('staff_name')
                    ->label('Staff')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime(config('filament-jnt.tables.datetime_format', 'Y-m-d H:i:s'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('scan_type_code')
                    ->label('Status Type')
                    ->options([
                        '10' => 'Parcel Pickup',
                        '20' => 'Outbound Scan',
                        '30' => 'Arrival',
                        '94' => 'Delivery Scan',
                        '100' => 'Parcel Signed',
                        '110' => 'Problematic',
                        '172' => 'Return Scan',
                        '173' => 'Return Sign',
                    ]),
                Filter::make('has_problem')
                    ->label('Has Problem')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('problem_type')),
                Filter::make('delivered')
                    ->label('Delivered')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('scan_type_code', '100')),
            ], layout: FiltersLayout::AboveContent)
            ->actions([
                ViewAction::make()
                    ->icon('heroicon-o-eye'),
            ])
            ->bulkActions([])
            ->defaultSort('scan_time', 'desc')
            ->paginated([25, 50, 100])
            ->poll(config('filament-jnt.polling_interval', '30s'));
    }

    private static function getStatusColor(?string $statusCode): string
    {
        return match ($statusCode) {
            '100' => 'success',      // Delivered
            '10', '20', '30', '94' => 'info',  // In transit
            '110', '172', '173' => 'warning', // Problem/Return
            '200', '201', '300', '301', '302', '303', '304', '305', '306' => 'danger', // Terminal
            default => 'secondary',
        };
    }
}
