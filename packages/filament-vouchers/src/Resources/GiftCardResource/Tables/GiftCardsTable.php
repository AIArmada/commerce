<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\GiftCardResource\Tables;

use AIArmada\FilamentVouchers\Actions\ActivateGiftCardAction;
use AIArmada\FilamentVouchers\Actions\SuspendGiftCardAction;
use AIArmada\FilamentVouchers\Actions\TopUpGiftCardAction;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardStatus;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardType;
use AIArmada\Vouchers\GiftCards\Models\GiftCard;
use Akaunting\Money\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class GiftCardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->copyable()
                    ->searchable()
                    ->sortable()
                    ->icon(Heroicon::Gift),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(static fn (GiftCardType | string $state): string => $state instanceof GiftCardType ? $state->color() : GiftCardType::from($state)->color())
                    ->formatStateUsing(static fn (GiftCardType | string $state): string => $state instanceof GiftCardType ? $state->label() : GiftCardType::from($state)->label())
                    ->sortable(),

                TextColumn::make('initial_balance')
                    ->label('Initial')
                    ->formatStateUsing(static fn (int $state, GiftCard $record): string => (string) Money::{$record->currency}($state))
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('current_balance')
                    ->label('Current')
                    ->formatStateUsing(static fn (int $state, GiftCard $record): string => (string) Money::{$record->currency}($state))
                    ->alignEnd()
                    ->sortable()
                    ->color(static fn (int $state): string => $state > 0 ? 'success' : 'danger'),

                TextColumn::make('balance_utilization')
                    ->label('Used')
                    ->formatStateUsing(static fn (float $state): string => number_format($state, 1) . '%')
                    ->badge()
                    ->color(static fn (float $state): string => match (true) {
                        $state >= 75 => 'success',
                        $state >= 50 => 'warning',
                        default => 'info',
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(static fn (GiftCardStatus | string $state): string => match ($state instanceof GiftCardStatus ? $state : GiftCardStatus::from($state)) {
                        GiftCardStatus::Active => 'success',
                        GiftCardStatus::Inactive => 'warning',
                        GiftCardStatus::Suspended => 'danger',
                        GiftCardStatus::Expired => 'gray',
                        GiftCardStatus::Exhausted => 'info',
                        GiftCardStatus::Cancelled => 'danger',
                    })
                    ->formatStateUsing(static fn (GiftCardStatus | string $state): string => $state instanceof GiftCardStatus ? $state->label() : GiftCardStatus::from($state)->label())
                    ->sortable(),

                IconColumn::make('has_pin')
                    ->label('PIN')
                    ->boolean()
                    ->state(fn (GiftCard $record): bool => $record->pin !== null)
                    ->tooltip('PIN protected'),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(static fn (): array => collect(GiftCardType::cases())
                        ->mapWithKeys(fn (GiftCardType $type): array => [$type->value => $type->label()])
                        ->toArray()),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(static fn (): array => collect(GiftCardStatus::cases())
                        ->mapWithKeys(fn (GiftCardStatus $status): array => [$status->value => $status->label()])
                        ->toArray()),

                Filter::make('with_balance')
                    ->label('With Balance')
                    ->query(static fn (Builder $query): Builder => $query->where('current_balance', '>', 0)),

                Filter::make('expiring_soon')
                    ->label('Expiring in 30 Days')
                    ->query(static fn (Builder $query): Builder => $query
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '>', now())
                        ->where('expires_at', '<=', now()->addDays(30))),
            ])
            ->actions([
                ActivateGiftCardAction::make(),
                TopUpGiftCardAction::make(),
                SuspendGiftCardAction::make(),
                ViewAction::make()
                    ->icon(Heroicon::OutlinedEye),
                EditAction::make()
                    ->icon(Heroicon::OutlinedPencil),
                DeleteAction::make()
                    ->icon(Heroicon::OutlinedTrash)
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Delete selected')
                        ->icon(Heroicon::OutlinedTrash)
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->paginated([25, 50, 100])
            ->striped();
    }
}
