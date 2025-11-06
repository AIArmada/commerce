<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherResource\Pages;

use AIArmada\FilamentVouchers\Resources\VoucherResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateVoucher extends CreateRecord
{
    protected static string $resource = VoucherResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
