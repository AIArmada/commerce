<?php

declare(strict_types=1);

use AIArmada\Communications\Contracts\CommunicationManager;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Facades\Communications;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Events\Actions\DispatchEventChangeChainAction;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Notifications\EventChangeNoticeNotification;
use Illuminate\Notifications\Notification;

it('creates change log with update for cancellations', function (): void {
    $event = Event::factory()->create();

    DispatchEventChangeChainAction::run(
        eventId: $event->id,
        changeType: 'cancelled',
        changeCategory: 'status',
        impactLevel: 'critical',
        requiresNotification: true,
        reason: 'Weather conditions',
    );

    expect($event->fresh()->changeLogs)->toHaveCount(1);
    expect($event->fresh()->updates)->toHaveCount(1);
});

it('dispatches critical changes through the communications bridge', function (): void {
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

    DispatchEventChangeChainAction::run(
        eventId: $event->id,
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
