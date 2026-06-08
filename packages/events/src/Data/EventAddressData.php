<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use Spatie\LaravelData\Data;

final class EventAddressData extends Data
{
    /**
     * @param  array<int, string>  $lines
     */
    public function __construct(
        public readonly string $label,
        public readonly array $lines = [],
        public readonly ?string $latitude = null,
        public readonly ?string $longitude = null,
        public readonly ?string $country = null,
        public readonly ?string $timezone = null,
    ) {}
}
