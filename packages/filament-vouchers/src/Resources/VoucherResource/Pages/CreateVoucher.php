<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherResource\Pages;

use AIArmada\FilamentVouchers\Resources\VoucherResource;
use AIArmada\FilamentVouchers\Support\ConditionTargetFormData;
use AIArmada\FilamentVouchers\Support\OwnerScopedQueries;
use Filament\Resources\Pages\CreateRecord;

final class CreateVoucher extends CreateRecord
{
    protected static string $resource = VoucherResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = parent::mutateFormDataBeforeCreate($data);

        $data = OwnerScopedQueries::enforceOwnerOnCreate($data);

        return $this->persistConditionTargetDefinition($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function persistConditionTargetDefinition(array $data): array
    {
        return ConditionTargetFormData::persist($data);
    }
}
