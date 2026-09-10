<?php

declare(strict_types=1);

use AIArmada\Communications\Actions\AttachCommunicationReferenceAction;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationReference;
use AIArmada\Communications\Services\CommunicationManagerService;
use AIArmada\Communications\Support\EventReferenceNormalizer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;

test('event references normalize models, arrays, and communication context', function (): void {
    $event = new class extends Model
    {
        use HasUuids;

        protected $guarded = [];

        public function getTable(): string
        {
            return 'event_reference_test_models';
        }
    };
    $event->setAttribute('id', 'event-123');

    $normalizer = app(EventReferenceNormalizer::class);

    expect($normalizer->normalize($event))
        ->toBe([
            'type' => $event->getMorphClass(),
            'id' => 'event-123',
        ])
        ->and($normalizer->normalize(['type' => 'events', 'id' => 123]))
        ->toBe(['type' => 'events', 'id' => '123'])
        ->and($normalizer->normalize(CommunicationContextData::from([
            'subjectType' => 'events',
            'subjectId' => 'event-123',
        ])))
        ->toBe(['type' => 'events', 'id' => 'event-123']);
});

test('attaching an event reference is idempotent and merges metadata', function (): void {
    $communication = Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'reference-test',
        'status' => CommunicationStatus::Draft,
    ]);

    $action = app(AttachCommunicationReferenceAction::class);
    $first = $action->handle(
        communicationId: $communication->id,
        referenceType: ['event_type' => 'events', 'event_id' => 'event-123'],
        role: 'event',
        metadata: ['batch_id' => 'batch-1'],
    );
    $second = $action->handle(
        communicationId: $communication->id,
        referenceType: 'events',
        referenceId: 'event-123',
        role: 'event',
        metadata: ['delivery_id' => 'delivery-1'],
    );

    expect($second->id)->toBe($first->id)
        ->and(CommunicationReference::query()->where('communication_id', $communication->id)->count())->toBe(1)
        ->and($second->fresh()->metadata)->toBe([
            'batch_id' => 'batch-1',
            'delivery_id' => 'delivery-1',
        ]);
});

test('manager accepts an event model as context and attaches its reference', function (): void {
    $notifiable = new class
    {
        use Notifiable;

        public function getKey(): string
        {
            return 'notifiable-event-reference';
        }
    };

    $event = new class extends Model
    {
        use HasUuids;

        protected $guarded = [];

        public function getTable(): string
        {
            return 'event_reference_test_models';
        }
    };
    $event->setAttribute('id', 'event-manager-123');

    $notification = new class extends Notification
    {
        public function via(object $notifiable): array
        {
            return [];
        }
    };

    $communication = app(CommunicationManagerService::class)->notify(
        $notifiable,
        $notification,
        $event,
    );

    $reference = CommunicationReference::query()
        ->where('communication_id', $communication->id)
        ->sole();

    expect($communication->subject_type)->toBe($event->getMorphClass())
        ->and($communication->subject_id)->toBe('event-manager-123')
        ->and($reference->reference_type)->toBe($event->getMorphClass())
        ->and($reference->reference_id)->toBe('event-manager-123')
        ->and($reference->role)->toBe('event');
});

test('manager accepts event context data and attaches its reference', function (): void {
    $notifiable = new class
    {
        use Notifiable;

        public function getKey(): string
        {
            return 'notifiable-context-reference';
        }
    };

    $notification = new class extends Notification
    {
        public function via(object $notifiable): array
        {
            return [];
        }
    };

    $communication = app(CommunicationManagerService::class)->notify(
        $notifiable,
        $notification,
        CommunicationContextData::from([
            'subjectType' => 'events',
            'subjectId' => 'event-context-123',
            'purpose' => 'event-notification',
        ]),
    );

    $reference = CommunicationReference::query()
        ->where('communication_id', $communication->id)
        ->sole();

    expect($reference->reference_type)->toBe('events')
        ->and($reference->reference_id)->toBe('event-context-123')
        ->and($reference->role)->toBe('event');
});
