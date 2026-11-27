<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Features\Rates\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Shipping\Features\Rates\Api\ManageRateConfiguration;
use AIArmada\Shipping\Features\Rates\Models\ShippingRate;
use AIArmada\Shipping\Features\Rates\Models\ShippingZone;
use AIArmada\Shipping\Features\Rates\Persistence\RateCache;
use AIArmada\Shipping\Features\Rates\Queries\ShippingZoneResolver;
use DomainException;
use Illuminate\Support\Facades\DB;

final class WriteRateConfiguration implements ManageRateConfiguration
{
    public function createZone(array $data): string
    {
        unset($data['owner_id'], $data['owner_type'], $data['owner_scope_key']);

        $zone = new ShippingZone($data);

        if ((bool) config('shipping.features.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            OwnerContext::assertResolvedOrExplicitGlobal($owner, 'Shipping zone creation requires an owner context.');
            $zone->forceFill(['owner_id' => $owner?->getKey(), 'owner_type' => $owner?->getMorphClass()]);
        }

        $zone->save();
        $this->invalidateQuotes();

        return (string) $zone->getKey();
    }

    public function updateZone(string $zoneId, array $data): void
    {
        unset($data['owner_id'], $data['owner_type'], $data['owner_scope_key']);

        DB::transaction(function () use ($zoneId, $data): void {
            $this->zone($zoneId)->update($data);
            $this->invalidateQuotes();
        });
    }

    public function createRate(string $zoneId, array $data): string
    {
        return DB::transaction(function () use ($zoneId, $data): string {
            $zone = $this->zone($zoneId);
            unset($data['zone_id']);

            $id = (string) $zone->rates()->create($data)->getKey();
            $this->invalidateQuotes();

            return $id;
        });
    }

    public function updateRate(string $rateId, array $data): void
    {
        DB::transaction(function () use ($rateId, $data): void {
            $rate = $this->rate($rateId);

            if (array_key_exists('zone_id', $data)) {
                if (! is_string($data['zone_id']) || $data['zone_id'] === '') {
                    throw new DomainException('A shipping rate must belong to a zone.');
                }

                $this->zone($data['zone_id']);
            }

            $rate->update($data);
            $this->invalidateQuotes();
        });
    }

    public function deleteRate(string $rateId): bool
    {
        return DB::transaction(function () use ($rateId): bool {
            $deleted = (bool) $this->rate($rateId)->delete();

            if ($deleted) {
                $this->invalidateQuotes();
            }

            return $deleted;
        });
    }

    public function deleteZone(string $zoneId): bool
    {
        return DB::transaction(function () use ($zoneId): bool {
            $zone = $this->zone($zoneId);

            if ($zone->rates()->exists()) {
                throw new DomainException('Remove the shipping rates before deleting the zone.');
            }

            $deleted = (bool) $zone->delete();

            if ($deleted) {
                $this->invalidateQuotes();
            }

            return $deleted;
        });
    }

    private function invalidateQuotes(): void
    {
        app(ShippingZoneResolver::class)->clearCache();

        $invalidate = static fn () => app(RateCache::class)->invalidateConfiguration();

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($invalidate);
        } else {
            $invalidate();
        }
    }

    private function zone(string $id): ShippingZone
    {
        if ((bool) config('shipping.features.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(ShippingZone::class, $id);
        }

        return ShippingZone::query()->lockForUpdate()->findOrFail($id);
    }

    private function rate(string $id): ShippingRate
    {
        $rate = ShippingRate::query()->findOrFail($id);
        $this->zone($rate->zone_id);
        $locked = ShippingRate::query()->lockForUpdate()->findOrFail($id);

        if ($locked->zone_id !== $rate->zone_id) {
            throw new DomainException('The rate zone has changed. Retry the operation.');
        }

        return $locked;
    }
}
