<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Contracts\EventSeatAllocator;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventPass;
use AIArmada\Events\Models\EventSeat;
use AIArmada\Events\Models\EventSeatAllocation;
use AIArmada\Events\Models\EventSeatSection;
use Carbon\CarbonImmutable;

final class DefaultEventSeatAllocator implements EventSeatAllocator
{
    public function allocate(EventPass $pass, array $preferences = []): ?EventSeatAllocation
    {
        $event = OwnerWriteGuard::findOrFailForOwner(Event::class, $pass->event_id);
        $sectionId = $preferences['event_seat_section_id'] ?? null;
        $seatId = $preferences['event_seat_id'] ?? null;

        if ($seatId) {
            $seat = EventSeat::query()->with('section.map')->find($seatId);

            if (! $seat || $seat->status !== 'available') {
                return null;
            }

            if ($seat->section?->map?->event_id !== $event->getKey()) {
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

            if (! $section || $section->map?->event_id !== $event->getKey()) {
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
