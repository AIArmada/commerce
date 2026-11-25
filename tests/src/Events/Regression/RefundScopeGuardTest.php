<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Commerce\Actions\ProcessEventRegistrationRefundAction;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
});

it('rejects a registration from another event before any authorization runs', function (): void {
    $eventA = Event::factory()->create();
    $eventB = Event::factory()->create();
    $registration = EventRegistration::factory()->create(['event_id' => $eventA->id]);
    $actor = User::factory()->create();

    Gate::before(function (): bool {
        throw new RuntimeException('Authorization must not run for a cross-event refund.');
    });

    expect(fn () => app(ProcessEventRegistrationRefundAction::class)->handle(
        eventId: (string) $eventB->getKey(),
        registrationId: (string) $registration->getKey(),
        actor: $actor,
        authorize: true,
    ))->toThrow(InvalidArgumentException::class, 'does not belong to the selected event');
});
