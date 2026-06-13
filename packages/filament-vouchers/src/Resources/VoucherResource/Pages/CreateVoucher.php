<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherResource\Pages;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentVouchers\Resources\VoucherResource;
use AIArmada\FilamentVouchers\Support\ConditionTargetFormData;
use AIArmada\Vouchers\Support\VoucherAffiliateOwnershipGuard;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

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

        $data = $this->enforceOwnerOnCreate($data);
        $data = VoucherAffiliateOwnershipGuard::sanitize($data);

        return $this->persistConditionTargetDefinition($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function enforceOwnerOnCreate(array $data): array
    {
        if (! config('vouchers.owner.enabled', false)) {
            return $data;
        }

        $owner = OwnerContext::resolve();

        if (! $owner instanceof Model) {
            $data['owner_type'] = null;
            $data['owner_id'] = null;

            return $data;
        }

        $data['owner_type'] = $owner->getMorphClass();
        $data['owner_id'] = (string) $owner->getKey();

        return $data;
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
