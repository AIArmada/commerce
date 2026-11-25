<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Api;

final readonly class VolumeTierSnapshot
{
    public function __construct(
        public int $minVolumeMinor,
        public string $currency,
        public int $rateBasisPoints,
    ) {}
}
