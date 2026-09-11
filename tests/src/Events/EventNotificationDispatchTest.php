<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Contracts\CommunicationManager;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Facades\Communications;
use AIArmada\Communications\Models\Communication;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Events\Actions\DispatchEventChangeChainAction;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventChangeLog;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventUpdate;
use AIArmada\Events\Notifications\EventChangeNoticeNotification;
use AIArmada\Events\Services\EventNotificationDispatcher;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\File;

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
        app(EventNotificationDispatcher::class)->dispatch($changeLog);
    });

    expect($capturedContext)->toBeInstanceOf(CommunicationContextData::class)
        ->and($capturedContext->subjectId)->toBe($event->id)
        ->and($capturedContext->purpose)->toBe('event-change-notice')
        ->and($capturedNotification)->toBeInstanceOf(EventChangeNoticeNotification::class)
        ->and($capturedNotification->title)->toBe('Event Change Notice')
        ->and($capturedNotification->message)->toBe('Weather conditions');
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

it('keeps the retired table-backed model names out of owned source and tests', function (): void {
    $retiredNames = [
        'EventNotification' . 'Batch',
        'EventNotification' . 'Delivery',
    ];
    $repositoryRoot = dirname(__DIR__, 3);
    $roots = [
        $repositoryRoot . '/packages/events/src',
        $repositoryRoot . '/packages/filament-events/src',
        $repositoryRoot . '/tests/src/Events',
        $repositoryRoot . '/tests/src/FilamentEvents',
    ];
    $references = [];

    foreach ($roots as $root) {
        foreach (File::allFiles($root) as $file) {
            if ($file->getPathname() === __FILE__) {
                continue;
            }

            $contents = File::get($file->getPathname());
            foreach ($retiredNames as $retiredName) {
                if (str_contains($contents, $retiredName)) {
                    $references[] = $file->getPathname() . ' contains ' . $retiredName;
                }
            }
        }
    }

    expect($references)->toBeEmpty();
});
