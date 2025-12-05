<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherWalletResource\Pages;

use AIArmada\FilamentVouchers\Resources\VoucherWalletResource;
use Filament\Resources\Pages\ListRecords;

final class ListVoucherWallets extends ListRecords
{
    protected static string $resource = VoucherWalletResource::class;
}
