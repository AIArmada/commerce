<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Events\Jobs\DispatchEventNotificationDelivery;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventNotificationBatch;
use AIArmada\Events\Models\EventNotificationDelivery;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Services\EventNotificationDispatcher;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
    config()->set('contacting.features.owner.enabled', false);
    config()->set('events.change_notices.channels', ['mail']);
    config()->set('events.change_notices.delivery.max_attempts', 3);
});

it('persists one delivery per recipient and channel before queueing work', function (): void {
    Queue::fake();

    OwnerContext::withOwner(null, function (): void {
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

        $batch = EventNotificationBatch::factory()->create([
            'event_id' => $event->id,
            'audience_scope' => 'registrants',
            'channels' => ['mail'],
            'status' => 'pending',
        ]);

        $dispatcher = app(EventNotificationDispatcher::class);
        $dispatcher->dispatch($batch);
        $dispatcher->dispatch($batch->fresh());

        $delivery = EventNotificationDelivery::query()->sole();

        expect($delivery->recipient_type)->toBe($registration->getMorphClass())
            ->and($delivery->recipient_id)->toBe($registration->id)
            ->and($delivery->channel)->toBe('mail')
            ->and($delivery->status)->toBe('pending')
            ->and($delivery->max_attempts)->toBe(3)
            ->and($batch->fresh()->status)->toBe('processing');

        Queue::assertPushed(DispatchEventNotificationDelivery::class, fn (DispatchEventNotificationDelivery $job): bool => $job->deliveryId === $delivery->id);
    });
});

it('delivers mail idempotently and completes the batch', function (): void {
    Mail::fake();

    OwnerContext::withOwner(null, function (): void {
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

        $batch = EventNotificationBatch::factory()->create([
            'event_id' => $event->id,
            'audience_scope' => 'registrants',
            'status' => 'processing',
        ]);
        $delivery = EventNotificationDelivery::query()->create([
            'event_notification_batch_id' => $batch->id,
            'recipient_type' => $registration->getMorphClass(),
            'recipient_id' => $registration->id,
            'channel' => 'mail',
            'status' => 'pending',
            'max_attempts' => 3,
        ]);

        app()->call([new DispatchEventNotificationDelivery($delivery->id), 'handle']);

        expect($delivery->fresh()->status)->toBe('sent')
            ->and($delivery->fresh()->attempt_count)->toBe(1)
            ->and($batch->fresh()->status)->toBe('sent')
            ->and($batch->fresh()->sent_at)->not->toBeNull();
    });
});

it('preserves safe failure codes and marks exhausted deliveries dead', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();
        $registration = EventRegistration::factory()->create([
            'event_id' => $event->id,
            'status' => 'confirmed',
        ]);
        $registration->participants()->create([
            'event_id' => $event->id,
            'name' => 'No Email',
            'is_primary' => true,
        ]);

        $batch = EventNotificationBatch::factory()->create([
            'event_id' => $event->id,
            'audience_scope' => 'registrants',
            'status' => 'processing',
        ]);
        $delivery = EventNotificationDelivery::query()->create([
            'event_notification_batch_id' => $batch->id,
            'recipient_type' => $registration->getMorphClass(),
            'recipient_id' => $registration->id,
            'channel' => 'mail',
            'status' => 'pending',
            'max_attempts' => 1,
        ]);
        $job = new DispatchEventNotificationDelivery($delivery->id);

        expect(fn () => app()->call([$job, 'handle']))
            ->toThrow(\RuntimeException::class, 'Event notification delivery failed.');

        $job->failed(new \RuntimeException('queue wrapper'));

        expect($delivery->fresh()->status)->toBe('dead')
            ->and($delivery->fresh()->last_error_code)->toBe('invalid_recipient')
            ->and($batch->fresh()->status)->toBe('failed');
    });
});

it('supports explicit retry and cancellation through the dispatcher module', function (): void {
    Queue::fake();

    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();
        $registration = EventRegistration::factory()->create([
            'event_id' => $event->id,
            'status' => 'confirmed',
        ]);
        $batch = EventNotificationBatch::factory()->create([
            'event_id' => $event->id,
            'audience_scope' => 'registrants',
            'status' => 'failed',
        ]);
        $delivery = EventNotificationDelivery::query()->create([
            'event_notification_batch_id' => $batch->id,
            'recipient_type' => $registration->getMorphClass(),
            'recipient_id' => $registration->id,
            'channel' => 'mail',
            'status' => 'dead',
            'attempt_count' => 3,
            'max_attempts' => 3,
            'dead_at' => now(),
            'last_error_code' => 'delivery_error',
        ]);

        $dispatcher = app(EventNotificationDispatcher::class);
        $dispatcher->dispatch($batch);

        expect($delivery->fresh()->status)->toBe('pending')
            ->and($delivery->fresh()->attempt_count)->toBe(0)
            ->and($delivery->fresh()->dead_at)->toBeNull()
            ->and($batch->fresh()->status)->toBe('processing');

        $dispatcher->cancel($batch->fresh());

        expect($delivery->fresh()->status)->toBe('cancelled')
            ->and($batch->fresh()->status)->toBe('cancelled')
            ->and($batch->fresh()->cancelled_at)->not->toBeNull();
    });
});
