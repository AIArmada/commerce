<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventAdministrationStats;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;

it('counts only the current owners event administration records', function (): void {
    $ownerA = User::query()->create(['name' => 'Stats A', 'email' => 'stats-a@example.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'Stats B', 'email' => 'stats-b@example.com', 'password' => 'secret']);

    OwnerContext::withOwner($ownerA, function (): void {
        $event = Event::factory()->create(['status' => 'published']);
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id, 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour()]);
        EventRegistration::factory()->create(['event_id' => $event->id, 'event_occurrence_id' => $occurrence->id]);
    });

    OwnerContext::withOwner($ownerB, function (): void {
        Event::factory()->count(2)->create(['status' => 'draft']);
    });

    $statsA = OwnerContext::withOwner($ownerA, fn () => app(EventAdministrationStats::class)->read());
    $statsB = OwnerContext::withOwner($ownerB, fn () => app(EventAdministrationStats::class)->read());

    expect($statsA->totalEvents)->toBe(1)
        ->and($statsA->publishedEvents)->toBe(1)
        ->and($statsA->upcomingOccurrences)->toBe(1)
        ->and($statsA->totalRegistrations)->toBe(1)
        ->and($statsB->totalEvents)->toBe(2)
        ->and($statsB->publishedEvents)->toBe(0)
        ->and($statsB->upcomingOccurrences)->toBe(0)
        ->and($statsB->totalRegistrations)->toBe(0);
});
