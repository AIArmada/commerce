<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Services;

use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Models\ShippingRate;
use AIArmada\Shipping\Models\ShippingZone;
use Illuminate\Support\Collection;

/**
 * Resolves shipping zones and rates for addresses.
 */
class ShippingZoneResolver
{
    /**
     * Resolve the matching zone for an address.
     */
    public function resolve(AddressData $address, ?int $ownerId = null, ?string $ownerType = null): ?ShippingZone
    {
        $query = ShippingZone::query()
            ->active()
            ->ordered();

        if ($ownerId !== null && $ownerType !== null) {
            $query->where('owner_id', $ownerId)
                ->where('owner_type', $ownerType);
        }

        $zones = $query->get();

        foreach ($zones as $zone) {
            if ($zone->matchesAddress($address)) {
                return $zone;
            }
        }

        // Fall back to default zone
        return $zones->firstWhere('is_default', true);
    }

    /**
     * Get all matching zones for an address (not just the first).
     *
     * @return Collection<int, ShippingZone>
     */
    public function resolveAll(AddressData $address, ?int $ownerId = null, ?string $ownerType = null): Collection
    {
        $query = ShippingZone::query()
            ->active()
            ->ordered();

        if ($ownerId !== null && $ownerType !== null) {
            $query->where('owner_id', $ownerId)
                ->where('owner_type', $ownerType);
        }

        return $query->get()
            ->filter(fn (ShippingZone $zone) => $zone->matchesAddress($address) || $zone->is_default);
    }

    /**
     * Get applicable rates for an address.
     *
     * @return Collection<int, ShippingRate>
     */
    public function getApplicableRates(
        AddressData $address,
        ?string $carrierCode = null,
        ?int $ownerId = null,
        ?string $ownerType = null
    ): Collection {
        $zone = $this->resolve($address, $ownerId, $ownerType);

        if ($zone === null) {
            return collect();
        }

        return $zone->rates()
            ->active()
            ->forCarrier($carrierCode)
            ->get();
    }

    /**
     * Check if an address is serviceable (has matching zone).
     */
    public function isServiceable(AddressData $address, ?int $ownerId = null, ?string $ownerType = null): bool
    {
        return $this->resolve($address, $ownerId, $ownerType) !== null;
    }

    /**
     * Test which zone an address matches (useful for debugging).
     *
     * @return array{matched: bool, zone: ?ShippingZone, reason: string}
     */
    public function test(AddressData $address, ?int $ownerId = null, ?string $ownerType = null): array
    {
        $zone = $this->resolve($address, $ownerId, $ownerType);

        if ($zone === null) {
            return [
                'matched' => false,
                'zone' => null,
                'reason' => 'No matching zone found for this address.',
            ];
        }

        $reason = $zone->is_default
            ? 'Matched to default zone.'
            : "Matched to zone '{$zone->name}' via {$zone->type} rule.";

        return [
            'matched' => true,
            'zone' => $zone,
            'reason' => $reason,
        ];
    }
}
