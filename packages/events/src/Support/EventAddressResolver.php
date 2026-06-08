<?php

declare(strict_types=1);

namespace AIArmada\Events\Support;

use AIArmada\Events\Contracts\EventAddressable;
use AIArmada\Events\Data\EventAddressData;
use AIArmada\Events\Models\EventSubLocation;
use Illuminate\Database\Eloquent\Model;

final class EventAddressResolver
{
    public function data(?Model $address): ?EventAddressData
    {
        if (! $address instanceof EventAddressable) {
            return null;
        }

        return $address->eventAddressData();
    }

    public function label(?Model $address, ?EventSubLocation $subLocation = null): ?string
    {
        $data = $this->data($address);

        if (! $data instanceof EventAddressData) {
            return null;
        }

        if (! $subLocation instanceof EventSubLocation) {
            return $data->label;
        }

        return mb_trim($subLocation->name . ', ' . $data->label);
    }

    /**
     * @return array<int, string>
     */
    public function lines(?Model $address): array
    {
        return $this->data($address)?->lines ?? [];
    }

    public function latitude(?Model $address): ?string
    {
        return $this->data($address)?->latitude;
    }

    public function longitude(?Model $address): ?string
    {
        return $this->data($address)?->longitude;
    }

    public function country(?Model $address): ?string
    {
        return $this->data($address)?->country;
    }

    public function timezone(?Model $address): ?string
    {
        return $this->data($address)?->timezone;
    }
}
