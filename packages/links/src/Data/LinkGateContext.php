<?php

declare(strict_types=1);

namespace AIArmada\Links\Data;

final readonly class LinkGateContext
{
    public function __construct(
        public string $id,
        public string $slug,
        public ?string $subjectType,
        public ?string $subjectId,
    ) {}
}
