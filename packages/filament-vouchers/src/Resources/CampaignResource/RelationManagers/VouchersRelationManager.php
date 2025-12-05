<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\CampaignResource\RelationManagers;

use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use Akaunting\Money\Money;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class VouchersRelationManager extends RelationManager
{
    protected static string $relationship = 'vouchers';

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?string $title = 'Campaign Vouchers';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->copyable()
                    ->searchable()
                    ->sortable()
                    ->icon(Heroicon::Tag),

                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(static fn (VoucherType $state): string => match ($state) {
                        VoucherType::Percentage => 'primary',
                        VoucherType::Fixed => 'success',
                        VoucherType::FreeShipping => 'warning',
                        VoucherType::BuyXGetY => 'info',
                        VoucherType::Tiered => 'danger',
                        VoucherType::Bundle => 'gray',
                        VoucherType::Cashback => 'success',
                    })
                    ->formatStateUsing(static fn (VoucherType $state): string => $state->label()),

                TextColumn::make('value')
                    ->label('Value')
                    ->formatStateUsing(static function ($state, Voucher $record): string {
                        $type = $record->type instanceof VoucherType ? $record->type : VoucherType::from((string) $record->type);

                        if ($type === VoucherType::Percentage) {
                            $percentage = (int) $state / 100;

                            return mb_rtrim(mb_rtrim(number_format($percentage, 2), '0'), '.').' %';
                        }

                        $currency = mb_strtoupper((string) config('filament-vouchers.default_currency', 'MYR'));

                        return (string) Money::{$currency}((int) $state);
                    })
                    ->alignEnd(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(static fn (VoucherStatus $state): string => match ($state) {
                        VoucherStatus::Active => 'success',
                        VoucherStatus::Paused => 'warning',
                        VoucherStatus::Expired => 'danger',
                        VoucherStatus::Depleted => 'gray',
                    })
                    ->formatStateUsing(static fn (VoucherStatus $state): string => $state->label()),

                TextColumn::make('usages_count')
                    ->label('Redeemed')
                    ->counts('usages')
                    ->alignCenter(),

                TextColumn::make('campaign_variant_id')
                    ->label('Variant')
                    ->badge()
                    ->color('info')
                    ->placeholder('No variant'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Link Voucher')
                    ->preloadRecordSelect(),
            ])
            ->actions([
                DetachAction::make()
                    ->label('Unlink'),
            ])
            ->bulkActions([
                DetachBulkAction::make()
                    ->label('Unlink selected'),
            ])
            ->defaultSort('code', 'asc');
    }
}
