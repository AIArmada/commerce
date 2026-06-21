<?php

declare(strict_types=1);

use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationEventSource;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationAttachment;
use AIArmada\Communications\Models\CommunicationBatch;
use AIArmada\Communications\Models\CommunicationContent;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationEvent;
use AIArmada\Communications\Models\CommunicationRecipient;
use AIArmada\Communications\Models\CommunicationReference;
use AIArmada\Communications\Models\CommunicationThread;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->communication = Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'test-purpose',
        'status' => CommunicationStatus::Draft,
    ]);
});

test('creates a communication with minimal attributes', function (): void {
    expect($this->communication->id)->toBeUuid();
    expect($this->communication->direction)->toBeInstanceOf(CommunicationDirection::class);
    expect($this->communication->direction->value)->toBe('outbound');
    expect($this->communication->category->value)->toBe('transactional');
    expect($this->communication->priority->value)->toBe('normal');
    expect($this->communication->purpose)->toBe('test-purpose');
    expect($this->communication->status->value)->toBe('draft');
});

test('casts enum attributes correctly', function (): void {
    $comm = Communication::find($this->communication->id);

    expect($comm->direction)->toBeInstanceOf(CommunicationDirection::class);
    expect($comm->category)->toBeInstanceOf(CommunicationCategory::class);
    expect($comm->priority)->toBeInstanceOf(CommunicationPriority::class);
    expect($comm->status)->toBeInstanceOf(CommunicationStatus::class);
});

test('casts lifecycle timestamps as CarbonImmutable', function (): void {
    $comm = Communication::find($this->communication->id);

    $comm->update([
        'scheduled_at' => '2026-06-20 12:00:00',
        'status' => CommunicationStatus::Scheduled,
    ]);

    $fresh = Communication::find($comm->id);
    expect($fresh->scheduled_at)->toBeInstanceOf(CarbonImmutable::class);
});

test('belongs to a batch', function (): void {
    $batch = CommunicationBatch::create([
        'name' => 'Test Batch',
        'purpose' => 'testing',
        'category' => 'transactional',
        'status' => 'pending',
        'requested_count' => 0,
    ]);

    $comm = Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'batch-test',
        'status' => CommunicationStatus::Draft,
        'batch_id' => $batch->id,
    ]);

    expect($comm->batch)->toBeInstanceOf(CommunicationBatch::class);
    expect($comm->batch->id)->toBe($batch->id);
});

test('belongs to a thread', function (): void {
    $thread = CommunicationThread::create([
        'title' => 'Test Thread',
        'status' => 'open',
        'opened_at' => CarbonImmutable::now(),
    ]);

    $comm = Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'thread-test',
        'status' => CommunicationStatus::Draft,
        'thread_id' => $thread->id,
    ]);

    expect($comm->thread)->toBeInstanceOf(CommunicationThread::class);
    expect($comm->thread->id)->toBe($thread->id);
});

test('has polymorphic subject relationship', function (): void {
    $comm = Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'subject-test',
        'status' => CommunicationStatus::Draft,
        'subject_type' => Communication::class,
        'subject_id' => $this->communication->id,
    ]);

    expect($comm->subject)->toBeInstanceOf(Communication::class);
    expect($comm->subject->id)->toBe($this->communication->id);
});

test('has many deliveries', function (): void {
    $recipient = CommunicationRecipient::create([
        'communication_id' => $this->communication->id,
        'role' => 'to',
    ]);

    $delivery = CommunicationDelivery::create([
        'communication_id' => $this->communication->id,
        'recipient_id' => $recipient->id,
        'channel' => 'mail',
        'provider' => 'array',
        'status' => DeliveryStatus::Pending,
        'attempt_count' => 0,
        'max_attempts' => 3,
    ]);

    expect($this->communication->deliveries)->toHaveCount(1);
    expect($this->communication->deliveries->first()->id)->toBe($delivery->id);
});

test('has many recipients', function (): void {
    $recipient = CommunicationRecipient::create([
        'communication_id' => $this->communication->id,
        'role' => 'to',
    ]);

    expect($this->communication->recipients)->toHaveCount(1);
    expect($this->communication->recipients->first()->id)->toBe($recipient->id);
});

test('has many contents', function (): void {
    $content = CommunicationContent::create([
        'communication_id' => $this->communication->id,
        'channel' => 'mail',
        'subject' => 'Test Subject',
        'content_text' => 'Test body content',
        'checksum' => md5('Test body content'),
        'rendered_at' => CarbonImmutable::now(),
    ]);

    expect($this->communication->contents)->toHaveCount(1);
    expect($this->communication->contents->first()->id)->toBe($content->id);
});

test('has many events', function (): void {
    $event = CommunicationEvent::create([
        'communication_id' => $this->communication->id,
        'source' => CommunicationEventSource::System,
        'event' => 'communication.created',
        'occurred_at' => CarbonImmutable::now(),
        'received_at' => CarbonImmutable::now(),
    ]);

    expect($this->communication->events)->toHaveCount(1);
    expect($this->communication->events->first()->id)->toBe($event->id);
});

test('has many references', function (): void {
    $reference = CommunicationReference::create([
        'communication_id' => $this->communication->id,
        'reference_type' => 'order',
        'reference_id' => 'ORD-001',
    ]);

    expect($this->communication->references)->toHaveCount(1);
    expect($this->communication->references->first()->id)->toBe($reference->id);
});

test('has many attachments', function (): void {
    $attachment = CommunicationAttachment::create([
        'communication_id' => $this->communication->id,
        'filename' => 'test.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1024,
    ]);

    expect($this->communication->attachments)->toHaveCount(1);
    expect($this->communication->attachments->first()->id)->toBe($attachment->id);
});

test('can have parent-child relationship', function (): void {
    $child = Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'child-test',
        'status' => CommunicationStatus::Draft,
        'parent_id' => $this->communication->id,
    ]);

    expect($child->parent)->toBeInstanceOf(Communication::class);
    expect($child->parent->id)->toBe($this->communication->id);
    expect($this->communication->children)->toHaveCount(1);
    expect($this->communication->children->first()->id)->toBe($child->id);
});

test('status transitions store timestamp', function (): void {
    $comm = Communication::find($this->communication->id);

    $comm->update(['status' => CommunicationStatus::Completed, 'completed_at' => CarbonImmutable::now()]);
    $fresh = Communication::find($comm->id);
    expect($fresh->status->value)->toBe('completed');
    expect($fresh->completed_at)->toBeInstanceOf(CarbonImmutable::class);

    $comm->update(['status' => CommunicationStatus::Failed, 'failed_at' => CarbonImmutable::now()]);
    $fresh = Communication::find($comm->id);
    expect($fresh->status->value)->toBe('failed');
    expect($fresh->failed_at)->toBeInstanceOf(CarbonImmutable::class);

    $comm->update(['status' => CommunicationStatus::Cancelled, 'cancelled_at' => CarbonImmutable::now()]);
    $fresh = Communication::find($comm->id);
    expect($fresh->status->value)->toBe('cancelled');
    expect($fresh->cancelled_at)->toBeInstanceOf(CarbonImmutable::class);
});

test('cascade delete removes related deliveries', function (): void {
    $recipient = CommunicationRecipient::create([
        'communication_id' => $this->communication->id,
        'role' => 'to',
    ]);

    CommunicationDelivery::create([
        'communication_id' => $this->communication->id,
        'recipient_id' => $recipient->id,
        'channel' => 'mail',
        'provider' => 'array',
        'status' => DeliveryStatus::Pending,
        'attempt_count' => 0,
        'max_attempts' => 3,
    ]);

    expect(CommunicationDelivery::query()->count())->toBe(1);

    $this->communication->delete();

    expect(CommunicationDelivery::query()->count())->toBe(0);
});

test('cascade delete removes related recipients and contents and events', function (): void {
    CommunicationRecipient::create(['communication_id' => $this->communication->id, 'role' => 'to']);
    CommunicationContent::create([
        'communication_id' => $this->communication->id,
        'channel' => 'mail',
        'subject' => 'S',
        'content_text' => 'B',
        'checksum' => md5('B'),
        'rendered_at' => CarbonImmutable::now(),
    ]);
    CommunicationEvent::create([
        'communication_id' => $this->communication->id,
        'source' => CommunicationEventSource::System,
        'event' => 'test',
        'occurred_at' => CarbonImmutable::now(),
        'received_at' => CarbonImmutable::now(),
    ]);

    $this->communication->delete();

    expect(CommunicationRecipient::query()->count())->toBe(0);
    expect(CommunicationContent::query()->count())->toBe(0);
    expect(CommunicationEvent::query()->count())->toBe(0);
});

test('stores and retrieves metadata', function (): void {
    $metadata = ['source' => 'api', 'version' => 1, 'tags' => ['welcome']];

    $this->communication->update(['metadata' => $metadata]);
    $fresh = Communication::find($this->communication->id);

    expect($fresh->metadata)->toBe($metadata);
    expect($fresh->metadata['source'])->toBe('api');
});
