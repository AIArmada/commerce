<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Data;

final readonly class PassHolderData
{
    public function __construct(
        public string $id,
        public ?string $holder_type = null,
        public ?string $holder_id = null,
        public ?string $name = null,
        public ?string $email = null,
    ) {}
}
