<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherWalletResource\Tables;

use AIArmada\FilamentVouchers\Resources\VoucherResource;
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
            ->modifyQueryUsing(static function (Builder $query): Builder {
                $wallet = $query->getModel();
                $voucherQuery = VoucherResource::getEloquentQuery();
                $voucherId = $voucherQuery->getModel()->qualifyColumn('id');

                return $query->select($wallet->qualifyColumn('*'))
                    ->addSelect([
                        'voucher_code' => (clone $voucherQuery)->select('code')->whereColumn($voucherId, $wallet->qualifyColumn('voucher_id')),
                        'voucher_name' => (clone $voucherQuery)->select('name')->whereColumn($voucherId, $wallet->qualifyColumn('voucher_id')),
                    ]);
            })
            ->columns([
                TextColumn::make('voucher_code')
                    ->label('Voucher Code')
                    ->copyable()
                    ->searchable(query: static fn (Builder $query, string $search): Builder => self::searchVoucher($query, 'code', $search))
                    ->sortable(query: static fn (Builder $query, string $direction): Builder => $query->orderBy('voucher_code', $direction))
                    ->icon(Heroicon::Ticket),

                TextColumn::make('voucher_name')
                    ->label('Voucher Name')
                    ->searchable(query: static fn (Builder $query, string $search): Builder => self::searchVoucher($query, 'name', $search))
                    ->toggleable(),

                TextColumn::make('holder_type')
                    ->label('Holder Type')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('holder_id')
                    ->label('Holder ID')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('claimed_at')
                    ->label('Claimed')
                    ->boolean(),

                TextColumn::make('claimed_at')
                    ->label('Claimed At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('redeemed_at')
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
                    ->query(static fn (Builder $query): Builder => $query->whereNotNull('claimed_at')),

                Filter::make('not_redeemed')
                    ->label('Not Redeemed')
                    ->query(static fn (Builder $query): Builder => $query->whereNull('redeemed_at')),

                Filter::make('redeemed')
                    ->label('Redeemed')
                    ->query(static fn (Builder $query): Builder => $query->whereNotNull('redeemed_at')),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->striped();
    }

    private static function searchVoucher(Builder $query, string $column, string $search): Builder
    {
        return $query->whereIn('voucher_id', VoucherResource::getEloquentQuery()->select('id')->where($column, 'like', '%' . $search . '%'));
    }
}
