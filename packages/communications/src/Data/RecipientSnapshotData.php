<?php

declare(strict_types=1);

namespace AIArmada\Communications\Data;

final readonly class RecipientSnapshotData
{
    public function __construct(
        public string $identifier,
        public ?string $displayName = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $locale = null,
        public ?string $timezone = null,
        public array $extra = [],
    ) {}
}
