<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Api;

final readonly class OwnedFormIdentity
{
    public function __construct(
        public string $id,
        public ?string $ownerType,
        public ?string $ownerId,
    ) {}
}
