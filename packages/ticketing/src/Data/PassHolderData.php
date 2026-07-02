<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Data;

use Spatie\LaravelData\Data;

class PassHolderData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $holder_type = null,
        public readonly ?string $holder_id = null,
        public readonly ?string $name = null,
        public readonly ?string $email = null,
    ) {}
}
