<?php

declare(strict_types=1);

namespace AIArmada\Events\Models\Concerns;

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\Addressable as AddressablePivot;
use AIArmada\Addressing\Support\AddressingTableResolver;
use AIArmada\Addressing\Support\AddressOwnerGuard;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait Addressable
{
    /**
     * @return MorphToMany<Address, $this>
     */
    public function addresses(): MorphToMany
    {
        $pivotTable = AddressingTableResolver::resolve('addressables');

        if ($this->shouldUseAddressing()) {
            $relation = $this->morphToMany(
                Address::class,
                'addressable',
                $pivotTable,
            )
                ->using(AddressablePivot::class)
                ->withPivot(['id', 'type', 'label', 'is_primary', 'valid_from', 'valid_until', 'owner_type', 'owner_id'])
                ->withTimestamps()
                ->orderBy("{$pivotTable}.is_primary", 'desc')
                ->orderBy("{$pivotTable}.created_at", 'desc');

            return AddressOwnerGuard::applyToRelation($relation);
        }

        return $this->morphToMany(
            Address::class,
            'addressable',
            $pivotTable,
        )->whereRaw('1 = 0');
    }

    public function getPrimaryAddressData(): ?AddressData
    {
        if ($this->shouldUseAddressing()) {
            $now = CarbonImmutable::now();
            $pivotTable = AddressingTableResolver::resolve('addressables');

            $address = $this->addresses()
                ->where("{$pivotTable}.is_primary", true)
                ->where(function (Builder $q) use ($now, $pivotTable): void {
                    $q->whereNull("{$pivotTable}.valid_from")
                        ->orWhere("{$pivotTable}.valid_from", '<=', $now);
                })
                ->where(function (Builder $q) use ($now, $pivotTable): void {
                    $q->whereNull("{$pivotTable}.valid_until")
                        ->orWhere("{$pivotTable}.valid_until", '>=', $now);
                })
                ->first();

            if ($address instanceof Address) {
                return $this->addressToData($address);
            }
        }

        return $this->buildAddressDataFromFlatColumns();
    }

    /**
     * @return Collection<int, Address>
     */
    public function getAddresses(): Collection
    {
        if ($this->shouldUseAddressing()) {
            /** @var Collection<int, Address> */
            return $this->addresses()->get();
        }

        return new Collection;
    }

    protected function shouldUseAddressing(): bool
    {
        return (bool) config('events.integrations.addressing_enabled')
            && class_exists(Address::class);
    }

    protected function addressToData(Address $address): AddressData
    {
        return AddressData::from($address->attributesToArray());
    }

    protected function buildAddressDataFromFlatColumns(): AddressData
    {
        return AddressData::from([
            'line1' => $this->line1 ?? null,
            'line2' => $this->line2 ?? null,
            'line3' => $this->line3 ?? null,
            'city' => $this->city ?? null,
            'state' => $this->state ?? null,
            'postcode' => $this->postcode ?? null,
            'country' => $this->country ?? null,
            'countryCode' => $this->country_code ?? null,
            'latitude' => $this->latitude ?? null,
            'longitude' => $this->longitude ?? null,
            'googleMapsUrl' => $this->google_maps_url ?? null,
            'wazeUrl' => $this->waze_url ?? null,
        ]);
    }
}
