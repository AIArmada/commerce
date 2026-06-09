<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\Tables;

use AIArmada\FilamentShipping\Actions\ApproveReturnAction;
use AIArmada\FilamentShipping\Actions\RejectReturnAction;
use AIArmada\Shipping\Enums\ReturnReason;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class ReturnAuthorizationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('rma_number')
                    ->label('RMA #')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('order_reference')
                    ->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'rejected' => 'danger',
                        'received' => 'primary',
                        'completed' => 'success',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('type')
                    ->badge(),

                TextColumn::make('reason')
                    ->formatStateUsing(fn ($state) => ReturnReason::tryFrom($state)?->getLabel() ?? $state),

                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'received' => 'Received',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),

                SelectFilter::make('type')
                    ->options([
                        'refund' => 'Refund',
                        'exchange' => 'Exchange',
                        'store_credit' => 'Store Credit',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                ApproveReturnAction::make(),
                RejectReturnAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
