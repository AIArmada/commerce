<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherWalletResource\Tables;

use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class VoucherWalletsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('voucher.code')
                    ->label('Voucher Code')
                    ->copyable()
                    ->searchable()
                    ->sortable()
                    ->icon(Heroicon::Ticket),

                TextColumn::make('voucher.name')
                    ->label('Voucher Name')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('owner_type')
                    ->label('Owner Type')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('owner_id')
                    ->label('Owner ID')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_claimed')
                    ->label('Claimed')
                    ->boolean(),

                TextColumn::make('claimed_at')
                    ->label('Claimed At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_redeemed')
                    ->label('Redeemed')
                    ->boolean(),

                TextColumn::make('redeemed_at')
                    ->label('Redeemed At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('claimed')
                    ->label('Claimed Only')
                    ->query(static fn (Builder $query): Builder => $query->where('is_claimed', true)),

                Filter::make('not_redeemed')
                    ->label('Not Redeemed')
                    ->query(static fn (Builder $query): Builder => $query->where('is_redeemed', false)),

                Filter::make('redeemed')
                    ->label('Redeemed')
                    ->query(static fn (Builder $query): Builder => $query->where('is_redeemed', true)),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->striped();
    }
}
