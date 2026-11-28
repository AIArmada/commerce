<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Data;

use Carbon\CarbonImmutable;

final readonly class TicketTypeData
{
    public function __construct(
        public string $name,
        public string $code,
        public ?string $id = null,
        public ?string $description = null,
        public ?string $access_type = null,
        public ?string $seating_mode = null,
        public ?int $price = null,
        public ?string $currency = null,
        public ?int $quota = null,
        public int $admits_quantity = 1,
        public ?int $min_quantity = null,
        public ?int $max_quantity = null,
        public ?CarbonImmutable $sales_starts_at = null,
        public ?CarbonImmutable $sales_ends_at = null,
        public string $status = 'active',
        public string $visibility = 'public',
        public int $sort_order = 0,
        public ?array $metadata = null,
    ) {}
}
