<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources;

use AIArmada\FilamentVouchers\Models\VoucherUsage;
use AIArmada\FilamentVouchers\Resources\VoucherUsageResource\Pages\ListVoucherUsages;
use AIArmada\FilamentVouchers\Resources\VoucherUsageResource\Tables\VoucherUsagesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class VoucherUsageResource extends Resource
{
    protected static ?string $model = VoucherUsage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Voucher Usage';

    protected static ?string $recordTitleAttribute = 'user_identifier';

    public static function table(Table $table): Table
    {
        return VoucherUsagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVoucherUsages::route('/'),
        ];
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('filament-vouchers.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-vouchers.resources.navigation_sort.voucher_usage', 45);
    }
}
