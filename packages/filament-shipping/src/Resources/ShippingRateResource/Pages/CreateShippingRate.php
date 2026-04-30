<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ShippingRateResource\Pages;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\FilamentShipping\Resources\ShippingRateResource;
use AIArmada\Shipping\Models\ShippingZone;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateShippingRate extends CreateRecord
{
    protected static string $resource = ShippingRateResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->validateZoneOwnership($data['zone_id'] ?? null);

        return $data;
    }

    private function validateZoneOwnership(?string $zoneId): void
    {
        if (! (bool) config('shipping.features.owner.enabled', false)) {
            return;
        }

        if ($zoneId === null) {
            return;
        }

        $owner = OwnerContext::resolve();

        if ($owner === null) {
            throw ValidationException::withMessages(['zone_id' => 'Owner context is required.']);
        }

        $exists = ShippingZone::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->where('id', $zoneId)
            ->where('owner_id', $owner->getKey())
            ->where('owner_type', $owner->getMorphClass())
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages(['zone_id' => 'The selected zone does not belong to your account.']);
        }
    }
}
