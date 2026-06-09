<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions\Resources\PromotionResource\RelationManagers;

use AIArmada\FilamentVouchers\Support\MoneyHelper;
use AIArmada\Promotions\Models\Promotion;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\Depleted;
use AIArmada\Vouchers\States\Expired;
use AIArmada\Vouchers\States\Paused;
use AIArmada\Vouchers\States\VoucherStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class IssuedVouchersRelationManager extends RelationManager
{
    protected static string $relationship = 'issuedVouchers';

    protected static ?string $title = 'Issued Vouchers';

    protected static ?string $modelLabel = 'issued voucher';

    protected static ?string $pluralModelLabel = 'issued vouchers';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->withCount('usages'))
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->copyable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(static fn (VoucherType | string $state): string => match ($state instanceof VoucherType ? $state : VoucherType::from($state)) {
                        VoucherType::Percentage => 'primary',
                        VoucherType::Fixed => 'success',
                        VoucherType::FreeShipping => 'warning',
                        VoucherType::BuyXGetY => 'info',
                        VoucherType::Tiered => 'secondary',
                        VoucherType::Bundle => 'primary',
                        VoucherType::Cashback => 'success',
                    })
                    ->formatStateUsing(static fn (VoucherType | string $state): string => $state instanceof VoucherType ? $state->label() : VoucherType::from($state)->label()),

                TextColumn::make('value')
                    ->label('Value')
                    ->formatStateUsing(static function (mixed $state, Voucher $record): string {
                        $rawType = $record->type;
                        $type = $rawType instanceof VoucherType ? $rawType : VoucherType::from((string) $rawType);

                        if ($type === VoucherType::Percentage) {
                            return MoneyHelper::formatPercentage((int) $state);
                        }

                        return MoneyHelper::formatMoney((int) $state, (string) $record->currency);
                    })
                    ->alignEnd(),

                TextColumn::make('usages_count')
                    ->label('Redeemed')
                    ->counts('usages')
                    ->alignCenter(),

                TextColumn::make('applied_count')
                    ->label('Applied')
                    ->alignCenter(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(static function (VoucherStatus | string $state): string {
                        $status = $state instanceof VoucherStatus ? $state : VoucherStatus::fromString($state);

                        return match (true) {
                            $status instanceof Active => 'success',
                            $status instanceof Paused => 'warning',
                            $status instanceof Expired => 'danger',
                            $status instanceof Depleted => 'gray',
                            default => 'gray',
                        };
                    })
                    ->formatStateUsing(static fn (VoucherStatus | string $state): string => VoucherStatus::labelFor($state)),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->placeholder('Never'),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('updated_at', 'desc')
            ->recordUrl(null);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Promotion && $ownerRecord::supportsIssuedVoucherTracking();
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
