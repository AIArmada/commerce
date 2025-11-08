<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherResource\Tables;

use AIArmada\Vouchers\Models\VoucherWallet;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class WalletEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('owner_type')
                    ->label('Owner Type')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->badge()
                    ->color(fn (string $state): string => match (class_basename($state)) {
                        'User' => 'success',
                        'Store' => 'info',
                        'Team' => 'warning',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): Heroicon => match (class_basename($state)) {
                        'User' => Heroicon::OutlinedUser,
                        'Store' => Heroicon::OutlinedBuildingStorefront,
                        'Team' => Heroicon::OutlinedUserGroup,
                        default => Heroicon::OutlinedQuestionMarkCircle,
                    })
                    ->sortable()
                    ->searchable(),

                TextColumn::make('owner_id')
                    ->label('Owner ID')
                    ->copyable()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->state(function (VoucherWallet $record): string {
                        if ($record->is_redeemed) {
                            return 'Redeemed';
                        }

                        if ($record->is_claimed) {
                            return 'Claimed';
                        }

                        return 'Available';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Redeemed' => 'danger',
                        'Claimed' => 'warning',
                        'Available' => 'success',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): Heroicon => match ($state) {
                        'Redeemed' => Heroicon::OutlinedCheckBadge,
                        'Claimed' => Heroicon::OutlinedShieldCheck,
                        'Available' => Heroicon::OutlinedSparkles,
                        default => Heroicon::OutlinedQuestionMarkCircle,
                    })
                    ->sortable(query: function ($query, string $direction): void {
                        $query->orderBy('is_redeemed', $direction)
                            ->orderBy('is_claimed', $direction);
                    }),

                IconColumn::make('is_expired')
                    ->label('Expired?')
                    ->state(fn (VoucherWallet $record): bool => $record->isExpired())
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedXCircle)
                    ->falseIcon(Heroicon::OutlinedCheckCircle)
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->tooltip(fn (VoucherWallet $record): string => $record->isExpired()
                        ? 'This voucher has expired'
                        : 'This voucher is still valid'
                    )
                    ->toggleable(),

                TextColumn::make('claimed_at')
                    ->label('Claimed At')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('redeemed_at')
                    ->label('Redeemed At')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),

                IconColumn::make('metadata')
                    ->label('Notes?')
                    ->boolean()
                    ->tooltip('Wallet entry contains metadata')
                    ->state(fn (VoucherWallet $record): bool => ! empty($record->metadata))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Added At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('owner_type')
                    ->label('Owner Type')
                    ->options([
                        'App\\Models\\User' => 'User',
                        'App\\Models\\Store' => 'Store',
                        'App\\Models\\Team' => 'Team',
                    ])
                    ->native(false),

                TernaryFilter::make('is_claimed')
                    ->label('Claimed')
                    ->nullable()
                    ->trueLabel('Claimed Only')
                    ->falseLabel('Unclaimed Only')
                    ->queries(
                        true: fn ($query) => $query->where('is_claimed', true),
                        false: fn ($query) => $query->where('is_claimed', false),
                        blank: fn ($query) => $query,
                    ),

                TernaryFilter::make('is_redeemed')
                    ->label('Redeemed')
                    ->nullable()
                    ->trueLabel('Redeemed Only')
                    ->falseLabel('Not Redeemed')
                    ->queries(
                        true: fn ($query) => $query->where('is_redeemed', true),
                        false: fn ($query) => $query->where('is_redeemed', false),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->actions([
                Action::make('markAsRedeemed')
                    ->label('Mark Redeemed')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (VoucherWallet $record): bool => ! $record->is_redeemed)
                    ->action(function (VoucherWallet $record): void {
                        $record->markAsRedeemed();
                    })
                    ->successNotification(
                        title: 'Marked as Redeemed',
                        body: 'The voucher has been marked as redeemed.',
                    ),

                Action::make('removeFromWallet')
                    ->label('Remove')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (VoucherWallet $record): void {
                        $record->delete();
                    })
                    ->successNotification(
                        title: 'Removed from Wallet',
                        body: 'The voucher has been removed from the wallet.',
                    ),

                Action::make('viewMetadata')
                    ->label('View Metadata')
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->color('info')
                    ->modalHeading('Wallet Entry Metadata')
                    ->modalContent(fn (VoucherWallet $record): string => view('filament-vouchers::components.json-viewer', [
                        'data' => $record->metadata ?? [],
                    ])->render())
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->visible(fn (VoucherWallet $record): bool => ! empty($record->metadata)),
            ])
            ->bulkActions([
                \Filament\Tables\Actions\BulkAction::make('markAsRedeemed')
                    ->label('Mark as Redeemed')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                        $records->each->markAsRedeemed();
                    })
                    ->successNotification(
                        title: 'Marked as Redeemed',
                        body: fn (\Illuminate\Database\Eloquent\Collection $records): string => "{$records->count()} voucher(s) have been marked as redeemed.",
                    ),

                \Filament\Tables\Actions\DeleteBulkAction::make()
                    ->label('Remove from Wallets')
                    ->successNotification(
                        title: 'Removed from Wallets',
                        body: fn (int $count): string => "{$count} voucher(s) have been removed from wallets.",
                    ),
            ])
            ->recordUrl(null);
    }
}
