<?php

declare(strict_types=1);

namespace AIArmada\Communications\Data;

final readonly class RecipientSnapshotData
{
    public function __construct(
        public string $identifier,
        public string | null $displayName = null,
        public string | null $email = null,
        public string | null $phone = null,
        public string | null $locale = null,
        public string | null $timezone = null,
        public array $extra = [],
    ) {}
}
