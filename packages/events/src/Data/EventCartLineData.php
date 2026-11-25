<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

final readonly class EventCartLineData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $ticketTypeId,
        public string $name,
        public int $price,
        public int $quantity,
        public array $attributes = [],
        public ?string $associatedClass = null,
        public ?string $associatedId = null,
    ) {}
}
