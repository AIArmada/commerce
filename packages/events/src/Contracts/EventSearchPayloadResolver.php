<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventSearchPayloadResolver
{
    public function resolve(array $payload): array;
}
