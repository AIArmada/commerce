<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use Carbon\CarbonImmutable;

it('counts blocked participants when calculating remaining occurrence capacity', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $originalBlockingStatuses = config('events.lifecycle.registration.capacity_blocking_statuses');
        config()->set('events.lifecycle.registration.capacity_blocking_statuses', [
            'pending',
            'confirmed',
            'checked_in',
            'no_show',
        ]);

        try {
            $event = Event::factory()->create();
            $occurrence = EventOccurrence::factory()->create([
                'event_id' => $event->id,
                'capacity' => 10,
                'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
                'ends_at' => CarbonImmutable::parse('2026-07-01 11:00:00'),
            ]);

            EventRegistration::query()->create([
                'event_id' => $event->id,
                'event_occurrence_id' => $occurrence->id,
                'registration_no' => 'REG-PENDING',
                'registration_type' => 'individual',
                'status' => 'pending',
                'source' => 'website',
                'total_participants' => 2,
                'currency' => 'MYR',
            ]);

            EventRegistration::query()->create([
                'event_id' => $event->id,
                'event_occurrence_id' => $occurrence->id,
                'registration_no' => 'REG-NO-SHOW',
                'registration_type' => 'individual',
                'status' => 'no_show',
                'source' => 'website',
                'total_participants' => 3,
                'currency' => 'MYR',
            ]);

            EventRegistration::query()->create([
                'event_id' => $event->id,
                'event_occurrence_id' => $occurrence->id,
                'registration_no' => 'REG-COMPLETED',
                'registration_type' => 'individual',
                'status' => 'completed',
                'source' => 'website',
                'total_participants' => 4,
                'currency' => 'MYR',
            ]);

            expect($occurrence->capacityRemaining())->toBe(5);
        } finally {
            config()->set('events.lifecycle.registration.capacity_blocking_statuses', $originalBlockingStatuses);
        }
    });
});
