<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Contracts\CommunicationManager;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Facades\Communications;
use AIArmada\Communications\Models\Communication;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Changes\Actions\DispatchEventChangeChainAction;
use AIArmada\Events\Features\Changes\Actions\DispatchEventChangeNoticeNotificationsAction;
use AIArmada\Events\Features\Changes\Models\EventChangeLog;
use AIArmada\Events\Features\Changes\Models\EventUpdate;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Notifications\EventChangeNoticeNotification;
use Illuminate\Notifications\Notification;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
    config()->set('contacting.features.owner.enabled', false);
    config()->set('communications.features.owner.enabled', false);
});

it('dispatches an event change notice through communications with event context', function (): void {
    $event = Event::factory()->create();
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->id,
        'status' => 'confirmed',
    ]);
    $participant = $registration->participants()->create([
        'event_id' => $event->id,
        'name' => 'Alice Example',
        'is_primary' => true,
    ]);
    $participant->addContactMethod(ContactMethodData::email('alice@example.com'));
    $changeLog = EventChangeLog::factory()->create([
        'event_id' => $event->id,
        'change_type' => 'cancelled',
        'impact_level' => 'critical',
        'reason' => 'Weather conditions',
    ]);
    EventUpdate::factory()->create([
        'event_id' => $event->id,
        'event_change_log_id' => $changeLog->id,
        'title' => 'Event Cancelled',
        'message' => 'Weather conditions',
    ]);

    $capturedContext = null;
    $capturedNotification = null;
    $manager = mock(CommunicationManager::class);
    $manager->shouldReceive('notify')
        ->once()
        ->withArgs(function (mixed $notifiable, mixed $notification, mixed $context) use (&$capturedContext, &$capturedNotification, $registration): bool {
            $capturedContext = $context;
            $capturedNotification = $notification;

            return $notifiable instanceof EventRegistration
                && $notifiable->is($registration)
                && $notification instanceof EventChangeNoticeNotification;
        })
        ->andReturn(new Communication);
    app()->instance(CommunicationManager::class, $manager);

    OwnerContext::withOwner(null, function () use ($changeLog): void {
        app(DispatchEventChangeNoticeNotificationsAction::class)->handle((string) $changeLog->getKey());
    });

    expect($capturedContext)->toBeInstanceOf(CommunicationContextData::class)
        ->and($capturedContext->subjectId)->toBe($event->id)
        ->and($capturedContext->purpose)->toBe('event-change-notice')
        ->and($capturedNotification)->toBeInstanceOf(EventChangeNoticeNotification::class)
        ->and($capturedNotification->data->title)->toBe('Event Change Notice')
        ->and($capturedNotification->data->message)->toBe('Weather conditions');
});

it('routes a required change-chain notice through the listener and communications', function (): void {
    $fake = Communications::fake();
    app()->instance(CommunicationManager::class, $fake);

    $event = Event::factory()->create();
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->id,
        'status' => 'confirmed',
    ]);
    $participant = $registration->participants()->create([
        'event_id' => $event->id,
        'name' => 'Alice Example',
        'is_primary' => true,
    ]);
    $participant->addContactMethod(ContactMethodData::email('alice@example.com'));

    app(DispatchEventChangeChainAction::class)->handle(
        eventId: (string) $event->getKey(),
        changeType: 'cancelled',
        changeCategory: 'status',
        impactLevel: 'critical',
        requiresNotification: true,
        reason: 'Weather conditions',
    );

    Communications::assertSent(function (mixed $notifiable, Notification $notification, mixed $context) use ($registration, $event): bool {
        return $notifiable instanceof EventRegistration
            && $notifiable->is($registration)
            && $notification instanceof EventChangeNoticeNotification
            && $context instanceof CommunicationContextData
            && $context->subjectId === $event->id
            && $context->purpose === 'event-change-notice';
    }, count: 1);
});
