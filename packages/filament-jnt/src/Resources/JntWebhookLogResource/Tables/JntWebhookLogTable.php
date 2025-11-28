<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Resources\JntWebhookLogResource\Tables;

use AIArmada\Jnt\Models\JntWebhookLog;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class JntWebhookLogTable
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
                    ->weight(FontWeight::SemiBold)
                    ->placeholder('—'),
                TextColumn::make('order_reference')
                    ->label('Order Ref')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('processing_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'processed' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'secondary',
                    })
                    ->sortable(),
                TextColumn::make('processing_error')
                    ->label('Error')
                    ->limit(50)
                    ->wrap()
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('processed_at')
                    ->label('Processed At')
                    ->dateTime(config('filament-jnt.tables.datetime_format', 'Y-m-d H:i:s'))
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime(config('filament-jnt.tables.datetime_format', 'Y-m-d H:i:s'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('processing_status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'processed' => 'Processed',
                        'failed' => 'Failed',
                    ]),
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
}
