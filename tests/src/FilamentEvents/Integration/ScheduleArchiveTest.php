<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Features\Catalog\Actions\ArchiveScheduleRecordAction;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use Illuminate\Auth\Access\AuthorizationException;

it('archives schedule records without cascading deletion of registrations', function (): void {
    config(['events.features.owner.enabled' => true, 'events.features.owner.auto_assign_on_create' => true]);
    $owner = User::factory()->create();
    $outsider = User::factory()->create();

    OwnerContext::withOwner($owner, function () use ($owner, $outsider): void {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id, 'status' => 'scheduled']);
        $session = EventSession::factory()->create(['event_id' => $event->id, 'event_occurrence_id' => $occurrence->id, 'status' => 'scheduled']);
        $registration = EventRegistration::factory()->create(['event_id' => $event->id, 'event_occurrence_id' => $occurrence->id, 'event_session_id' => $session->id]);

        $action = app(ArchiveScheduleRecordAction::class);

        expect(fn () => $action->handle('session', $session->id, $outsider))->toThrow(AuthorizationException::class);

        $action->handle('session', $session->id, $owner);
        $action->handle('session', $session->id, $owner);
        $action->handle('occurrence', $occurrence->id, $owner);

        expect($session->refresh()->status->getValue())->toBe('archived')
            ->and($session->archived_at)->not->toBeNull()
            ->and($occurrence->refresh()->status->getValue())->toBe('archived')
            ->and($registration->fresh())->not->toBeNull();
    });
});
