<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventReferenceResolver
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(string $referenceId): array;
}
