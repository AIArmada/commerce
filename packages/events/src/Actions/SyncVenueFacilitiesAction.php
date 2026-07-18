<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Models\FacilityType;
use AIArmada\Events\Models\Venue;
use AIArmada\Events\Models\VenueFacility;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SyncVenueFacilitiesAction
{
    /**
     * @param  array<int, array{code: string, availability?: string, visibility?: string}>  $facilities
     */
    public function handle(Venue $venue, array $facilities): int
    {
        $codes = array_unique(array_map(fn (array $f): string => $f['code'], $facilities));

        /** @var Collection<string, FacilityType> $types */
        $types = FacilityType::query()
            ->where('is_active', true)
            ->whereIn('code', $codes)
            ->get()
            ->keyBy(fn (FacilityType $t): string => $t->code);

        $unknown = array_diff($codes, $types->keys()->all());

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown facility codes: ' . implode(', ', $unknown));
        }

        $synced = 0;

        DB::transaction(function () use ($venue, $facilities, $types, &$synced): void {
            $existing = VenueFacility::query()
                ->where('venue_id', $venue->getKey())
                ->whereNull('venue_space_id')
                ->get()
                ->keyBy(fn (VenueFacility $vf): string => $vf->facility_type_id);

            $incomingIds = [];

            foreach ($facilities as $facility) {
                $type = $types[$facility['code']];

                VenueFacility::query()->updateOrCreate(
                    [
                        'venue_id' => $venue->getKey(),
                        'facility_type_id' => $type->getKey(),
                        'venue_space_id' => null,
                    ],
                    [
                        'availability' => $facility['availability'] ?? 'available',
                        'visibility' => $facility['visibility'] ?? 'public',
                        'quantity' => null,
                        'capacity' => null,
                        'is_free' => null,
                        'fee_amount' => null,
                        'currency' => null,
                        'location_label' => null,
                        'notes' => null,
                        'verified_at' => null,
                    ],
                );

                $incomingIds[] = $type->getKey();
                $synced++;
            }

            $toRemove = $existing->keys()->diff($incomingIds);

            if ($toRemove->isNotEmpty()) {
                VenueFacility::query()
                    ->where('venue_id', $venue->getKey())
                    ->whereNull('venue_space_id')
                    ->whereIn('facility_type_id', $toRemove->all())
                    ->delete();
            }
        });

        return $synced;
    }
}
