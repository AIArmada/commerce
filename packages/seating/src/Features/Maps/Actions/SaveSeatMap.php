<?php

declare(strict_types=1);

namespace AIArmada\Seating\Features\Maps\Actions;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Seating\Features\Maps\Models\SeatMap;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

final readonly class SaveSeatMap
{
    public function handle(array $data, ?SeatMap $record = null, ?Model $host = null): SeatMap
    {
        $attributes = Validator::make(Arr::only($data, ['name', 'slug', 'version', 'status', 'layout_metadata']), [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'version' => ['sometimes', 'integer', 'min:1'],
            'status' => ['required', 'in:active,inactive,archived'],
            'layout_metadata' => ['nullable', 'array'],
        ])->validate();

        return (new SeatMap)->getConnection()->transaction(function () use ($attributes, $record, $host): SeatMap {
            if ($host !== null && (! $host->exists || ! OwnerUiScope::canMutateHost($host))) {
                throw new AuthorizationException('The seat-map host is not writable in the current owner scope.');
            }

            if ($record === null) {
                OwnerUiScope::resolveOwner(SeatMap::class);
                $map = new SeatMap;

                if ($host !== null) {
                    $map->seatable()->associate($host);
                }
            } else {
                /** @var SeatMap $map */
                $map = OwnerUiScope::apply(SeatMap::query(), includeGlobal: false)->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

                if ($host !== null && ($map->seatable_type !== $host->getMorphClass() || (string) $map->seatable_id !== (string) $host->getKey())) {
                    throw new AuthorizationException('The seat map does not belong to this host.');
                }
            }

            $map->fill($attributes);
            $map->saveOrFail();

            return $map;
        });
    }
}
