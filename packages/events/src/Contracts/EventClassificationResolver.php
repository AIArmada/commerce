<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventClassificationResolver
{
    /**
     * @return array<int|string, mixed>
     */
    public function resolve(string $eventId): array;
}
