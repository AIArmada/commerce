<?php

declare(strict_types=1);

namespace AIArmada\Seating\Features\Maps\Actions;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Seating\Models\SeatMap;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

final readonly class DeleteSeatMap
{
    public function handle(SeatMap $record, ?Model $host = null): bool
    {
        return $record->getConnection()->transaction(function () use ($record, $host): bool {
            $map = OwnerUiScope::apply(SeatMap::query(), includeGlobal: false)->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if ($host !== null && ($map->seatable_type !== $host->getMorphClass() || (string) $map->seatable_id !== (string) $host->getKey())) {
                throw new AuthorizationException('The seat map does not belong to this host.');
            }

            return (bool) $map->delete();
        });
    }
}
