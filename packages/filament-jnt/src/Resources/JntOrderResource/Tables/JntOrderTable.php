<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Resources\JntOrderResource\Tables;

use AIArmada\Jnt\Models\JntOrder;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class JntOrderTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                TextColumn::make('order_id')
                    ->label('Order ID')
                    ->icon('heroicon-o-tag')
                    ->iconColor('primary')
                    ->copyable()
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('tracking_number')
                    ->label('Tracking #')
                    ->icon('heroicon-o-truck')
                    ->iconColor('success')
                    ->copyable()
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('customer_code')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('express_type')
                    ->label('Type')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('service_type')
                    ->label('Service')
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (JntOrder $record): string => self::getStatusColor($record->last_status_code))
                    ->searchable()
                    ->sortable(),
                IconColumn::make('has_problem')
                    ->label('Problem')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('gray')
                    ->sortable(),
                TextColumn::make('chargeable_weight')
                    ->label('Weight')
                    ->suffix(' kg')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('package_value')
                    ->label('Value')
                    ->money('MYR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cod_value')
                    ->label('COD')
                    ->money('MYR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                TextColumn::make('delivered_at')
                    ->label('Delivered')
                    ->dateTime(config('filament-jnt.tables.datetime_format', 'Y-m-d H:i:s'))
                    ->sortable()
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime(config('filament-jnt.tables.datetime_format', 'Y-m-d H:i:s'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('express_type')
                    ->label('Express Type')
                    ->options([
                        'EZ' => 'Domestic Standard',
                        'EX' => 'Express Next Day',
                        'FD' => 'Fresh Delivery',
                    ]),
                SelectFilter::make('service_type')
                    ->label('Service Type')
                    ->options([
                        '1' => 'Door to Door',
                        '6' => 'Walk-In',
                    ]),
                Filter::make('has_problem')
                    ->label('Has Problem')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('has_problem', true)),
                Filter::make('delivered')
                    ->label('Delivered')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('delivered_at')),
                Filter::make('pending')
                    ->label('Pending Delivery')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNull('delivered_at')),
            ], layout: FiltersLayout::AboveContent)
            ->actions([
                ViewAction::make()
                    ->icon('heroicon-o-eye'),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
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
