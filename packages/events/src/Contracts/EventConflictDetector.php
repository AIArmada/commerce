<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventConflictDetector
{
    public function detectConflicts(mixed $target, array $context = []): array;
}
