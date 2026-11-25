<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Api;

final readonly class EventOwnerRef
{
    public function __construct(
        public string $type,
        public string $id,
    ) {}
}
