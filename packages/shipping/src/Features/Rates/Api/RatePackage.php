<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Features\Rates\Api;

final readonly class RatePackage
{
    public function __construct(
        public int $weight, // in grams
        public ?int $length = null, // in cm
        public ?int $width = null,
        public ?int $height = null,
        public ?int $declaredValue = null, // in cents
        public ?string $packagingType = null,
        public int $quantity = 1,
    ) {}
}
