<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class TicketTypeData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $code,
        public readonly ?string $description = null,
        public readonly ?int $price = null,
        public readonly ?string $currency = null,
        public readonly int $admits_quantity = 1,
        public readonly ?int $min_quantity = null,
        public readonly ?int $max_quantity = null,
        public readonly ?CarbonImmutable $sales_starts_at = null,
        public readonly ?CarbonImmutable $sales_ends_at = null,
        public readonly string $status = 'active',
        public readonly string $visibility = 'public',
        public readonly int $sort_order = 0,
        public readonly ?array $metadata = null,
    ) {}
}
