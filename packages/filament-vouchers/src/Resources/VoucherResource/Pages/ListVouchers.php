<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherResource\Pages;

use AIArmada\FilamentVouchers\Actions\BulkGenerateVouchersAction;
use AIArmada\FilamentVouchers\Resources\VoucherResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListVouchers extends ListRecords
{
    protected static string $resource = VoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            BulkGenerateVouchersAction::make(),
        ];
    }
}
