<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventAdministrativeCheckIn;
use AIArmada\Events\Contracts\EventCheckInService;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use Illuminate\Auth\Access\AuthorizationException;

it('rejects unauthorized operators for both administrative check-in paths before writing attendance', function (): void {
    config(['events.features.owner.enabled' => true, 'events.features.owner.auto_assign_on_create' => true]);
    $owner = User::factory()->create();
    $outsider = User::factory()->create();

    OwnerContext::withOwner($owner, function () use ($outsider): void {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
        $registration = EventRegistration::factory()->create(['event_id' => $event->id, 'event_occurrence_id' => $occurrence->id]);
        $pass = createEventPass(createEventTicketType($event), $registration);

        $service = Mockery::mock(EventCheckInService::class);
        $service->shouldNotReceive('checkInWithResult');
        app()->instance(EventCheckInService::class, $service);

        $admin = app(EventAdministrativeCheckIn::class);

        expect(fn () => $admin->checkInPass($pass->id, $outsider))->toThrow(AuthorizationException::class)
            ->and(fn () => $admin->checkInWalkIn($event->id, $outsider))->toThrow(AuthorizationException::class)
            ->and(fn () => $admin->checkInWalkIn($event->id, null))->toThrow(AuthorizationException::class);
    });
});
