<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\Events\Contracts\EventSeatAllocator;
use AIArmada\Events\Models\EventPass;
use AIArmada\Events\Models\EventSeat;
use AIArmada\Events\Models\EventSeatAllocation;
use AIArmada\Events\Models\EventSeatSection;
use AIArmada\Events\Support\EventWriteGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class DefaultEventSeatAllocator implements EventSeatAllocator
{
    public function allocate(EventPass $pass, array $preferences = []): ?EventSeatAllocation
    {
        $event = EventWriteGuard::findOrFail($pass->event_id);

        return DB::transaction(function () use ($event, $pass, $preferences): ?EventSeatAllocation {
            return $this->allocateWithinTransaction($pass, $preferences, $event->getKey());
        });
    }

    private function allocateWithinTransaction(EventPass $pass, array $preferences, string | int $eventId): ?EventSeatAllocation
    {
        $sectionId = $preferences['event_seat_section_id'] ?? null;
        $seatId = $preferences['event_seat_id'] ?? null;

        if ($seatId) {
            $seat = EventSeat::query()
                ->with('section.map')
                ->lockForUpdate()
                ->find($seatId);

            if (! $seat || $seat->status !== 'available') {
                return null;
            }

            if ($seat->section?->map?->event_id !== $eventId) {
                return null;
            }

            if ($sectionId !== null && $seat->section?->id !== $sectionId) {
                return null;
            }

            $seat->update(['status' => 'allocated']);

            return $this->createAllocation($pass, $seat->section?->id, $seatId);
        }

        if ($sectionId) {
            $section = EventSeatSection::query()->with('map')->find($sectionId);

            if (! $section || $section->map?->event_id !== $eventId) {
                return null;
            }

            return $this->createAllocation($pass, $section->id);
        }

        return null;
    }

    private function createAllocation(EventPass $pass, ?string $sectionId = null, ?string $seatId = null): EventSeatAllocation
    {
        return EventSeatAllocation::query()->create([
            'event_id' => $pass->event_id,
            'event_occurrence_id' => $pass->event_occurrence_id,
            'event_session_id' => $pass->event_session_id,
            'event_pass_id' => $pass->id,
            'event_seat_section_id' => $sectionId,
            'event_seat_id' => $seatId,
            'allocation_type' => $seatId ? 'reserved_seat' : 'general_section',
            'status' => 'allocated',
            'allocated_at' => CarbonImmutable::now(),
        ]);
    }
}
