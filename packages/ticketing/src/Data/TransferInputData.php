<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Data;


final readonly class TransferInputData
{
    public function __construct(
        public string $pass_id,
        public ?string $holder_type = null,
        public ?string $holder_id = null,
        public ?string $name = null,
        public ?string $email = null,
        public ?string $reason = null,
        public ?string $existing_holder_id = null,
        public ?array $metadata = null,
    ) {}
}
