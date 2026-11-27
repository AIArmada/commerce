<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Aggregate\Actions;

use AIArmada\Communications\Contracts\CommunicationCreator;
use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Enums\CommunicationStatus;
use Carbon\CarbonImmutable;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Events\CommunicationCreated;
use AIArmada\Communications\Events\CommunicationQueued;
use AIArmada\Communications\Events\CommunicationScheduled;
use AIArmada\Communications\Models\Communication;
use Illuminate\Support\Facades\Event;

final readonly class CreateCommunicationAction implements CommunicationCreator
{
    public function __construct(
        private PayloadRedactor $redactor,
    ) {}

    public function handle(CommunicationContextData $context, bool $queued = false): string
    {
        $communication = new Communication;
        $communication->direction = $context->direction;
        $communication->category = $context->category;
        $communication->priority = $context->priority;
        $communication->purpose = $context->purpose;
        $communication->status = $queued ? CommunicationStatus::Queued : CommunicationStatus::Draft;
        $communication->queued_at = $queued ? CarbonImmutable::now() : null;
        $communication->idempotency_key = $context->idempotencyKey;
        $communication->locale = $context->locale;
        $communication->timezone = $context->timezone;
        $communication->scheduled_at = $context->scheduledAt !== null ? CarbonImmutable::parse($context->scheduledAt) : null;
        $communication->expires_at = $context->expiresAt !== null ? CarbonImmutable::parse($context->expiresAt) : null;
        $communication->metadata = $this->redactor->redact($context->metadata);
        $communication->subject_type = $context->subjectType;
        $communication->subject_id = $context->subjectId;
        $communication->sender_type = $context->senderType;
        $communication->sender_id = $context->senderId;
        $communication->batch_id = $context->batchId;
        $communication->thread_id = $context->threadId;
        $communication->parent_id = $context->parentId;
        $communication->save();

        Event::dispatch(new CommunicationCreated(
            communicationId: $communication->id,
            category: $communication->category->value,
            direction: $communication->direction->value,
            purpose: $communication->purpose,
        ));

        if ($communication->scheduled_at !== null) {
            Event::dispatch(new CommunicationScheduled(
                communicationId: $communication->id,
                scheduledAt: $communication->scheduled_at->toIso8601String(),
            ));
        }

        if ($queued) {
            Event::dispatch(new CommunicationQueued(communicationId: $communication->id));
        }

        return $communication->id;
    }
}
