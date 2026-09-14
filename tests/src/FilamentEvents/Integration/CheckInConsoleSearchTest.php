<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\FilamentEvents\Pages\CheckInConsole;

function checkInConsoleResults(CheckInConsole $page, string $search): array
{
    $page->passOrRegistration = $search;

    $method = new ReflectionMethod(CheckInConsole::class, 'getFilteredQuery');
    $method->setAccessible(true);

    return $method->invoke($page)->pluck('pass_no')->all();
}

it('treats like wildcards in the search box as literal characters', function (): void {
    $owner = User::query()->create([
        'name' => 'CheckIn Owner',
        'email' => 'checkin-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($owner, function (): void {
        $tag = uniqid();
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
        $registration = EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'registration_no' => 'REG-' . $tag,
        ]);
        $ticketType = createEventTicketType($event);

        createEventPass($ticketType, $registration, ['pass_no' => 'PASS-' . $tag]);
        createEventPass($ticketType, $registration, ['pass_no' => 'PASS-' . $tag . '2']);

        $page = new CheckInConsole;

        expect(checkInConsoleResults($page, 'PASS-' . $tag))->toHaveCount(2)
            ->and(checkInConsoleResults($page, 'PASS-' . $tag . '_'))->toHaveCount(0)
            ->and(checkInConsoleResults($page, 'PASS-' . $tag . '%'))->toHaveCount(0)
            ->and(checkInConsoleResults($page, 'REG-' . $tag))->toHaveCount(2);
    });
});
