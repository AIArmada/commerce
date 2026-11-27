<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Features\Rates\Api;

final readonly class RateAddress
{
    public function __construct(
        public string $name,
        public string $phone,
        public string $line1,
        public string $postcode,
        public string $country = 'MY',
        public ?string $company = null,
        public ?string $email = null,
        public ?string $line2 = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public bool $isResidential = true,
    ) {}
}
