<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

final readonly class AwardRecipient
{
    public function __construct(
        public string $id,
        public string $morphType,
        public string $connectionName,
        public ?string $ownerType,
        public ?string $ownerId,
    ) {}
}
