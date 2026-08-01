<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventSearchRelationProvider
{
    /**
     * @return array<int|string, mixed>
     */
    public function relations(): array;
}
