<?php

declare(strict_types=1);

namespace AIArmada\Events\Models\Concerns;

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\Addressable as AddressablePivot;
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
        if ($this->shouldUseAddressing()) {
            return $this->morphToMany(
                Address::class,
                'addressable',
                config('addressing.tables.addressables', 'addressables'),
            )
                ->using(AddressablePivot::class)
                ->withPivot(['id', 'type', 'label', 'is_primary', 'valid_from', 'valid_until'])
                ->withTimestamps()
                ->orderBy('addressables.is_primary', 'desc')
                ->orderBy('addressables.created_at', 'desc');
        }

        return $this->morphToMany(
            Address::class,
            'addressable',
            config('addressing.tables.addressables', 'addressables'),
        )->whereRaw('1 = 0');
    }

    public function getPrimaryAddressData(): ?AddressData
    {
        if ($this->shouldUseAddressing()) {
            $now = now();

            $address = $this->addresses()
                ->where('addressables.is_primary', true)
                ->where(function (Builder $q) use ($now): void {
                    $q->whereNull('addressables.valid_from')
                        ->orWhere('addressables.valid_from', '<=', $now);
                })
                ->where(function (Builder $q) use ($now): void {
                    $q->whereNull('addressables.valid_until')
                        ->orWhere('addressables.valid_until', '>=', $now);
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
        return AddressData::from($address->toArray());
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
