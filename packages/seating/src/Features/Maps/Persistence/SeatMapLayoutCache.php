<?php

declare(strict_types=1);

namespace AIArmada\Seating\Features\Maps\Persistence;

use AIArmada\CommerceSupport\Persistence\OwnerCache;
use Illuminate\Database\Eloquent\Model;

final readonly class SeatMapLayoutCache
{
    /**
     * @param  callable(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    public function remember(Model $owner, string $logicalKey, callable $callback): array
    {
        return OwnerCache::remember($owner, $logicalKey, 60, $callback);
    }
}
