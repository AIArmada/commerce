<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherUsageResource\Pages;

use AIArmada\FilamentVouchers\Resources\VoucherUsageResource;
use Filament\Resources\Pages\ListRecords;

final class ListVoucherUsages extends ListRecords
{
    protected static string $resource = VoucherUsageResource::class;

    public function getTitle(): string
    {
        return 'Voucher Usage';
    }
}
