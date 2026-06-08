<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use Illuminate\Database\Eloquent\Model;

interface EventAssetResolver
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function resolve(Model $subject): array;
}
