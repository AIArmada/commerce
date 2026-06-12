<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface CanBeGeocodedForEvents
{
    public function eventGeocodingAddress(): ?string;

    public function markEventGeocoded(array $result): void;
}
