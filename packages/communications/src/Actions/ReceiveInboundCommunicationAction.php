<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\RecipientRole;
use AIArmada\Communications\Events\InboundCommunicationReceived;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationContent;
use AIArmada\Communications\Models\CommunicationRecipient;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class ReceiveInboundCommunicationAction
{
    public function __construct(
        private readonly ResolveCommunicationThreadAction $threadResolver,
        private readonly PayloadRedactor $redactor,
    ) {}

    public function handle(
        string $fromType,
        string $fromId,
        string $channel,
        ?string $body = null,
        ?string $subject = null,
        ?string $threadExternalId = null,
        ?string $threadTitle = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?string $senderType = null,
        ?string $senderId = null,
        ?array $metadata = null,
    ): Communication {
        $this->validateMorph('from', $fromType, $fromId);
        $this->validateOptionalMorph('subject', $subjectType, $subjectId);
        $this->validateOptionalMorph('sender', $senderType, $senderId);

        return DB::transaction(function () use (
            $fromType,
            $fromId,
            $channel,
            $body,
            $subject,
            $threadExternalId,
            $threadTitle,
            $subjectType,
            $subjectId,
            $senderType,
            $senderId,
            $metadata,
        ) {
            $thread = $this->threadResolver->handle(
                channel: $channel,
                externalThreadId: $threadExternalId,
                title: $threadTitle,
                subjectType: $subjectType,
                subjectId: $subjectId,
            );

            $communication = new Communication;
            $communication->direction = CommunicationDirection::Inbound;
            $communication->category = CommunicationCategory::Support;
            $communication->priority = CommunicationPriority::Normal;
            $communication->purpose = 'inbound';
            $communication->status = CommunicationStatus::Completed;
            $communication->thread_id = $thread->id;
            $communication->subject_type = $subjectType;
            $communication->subject_id = $subjectId;
            $communication->sender_type = $senderType;
            $communication->sender_id = $senderId;
            $communication->completed_at = CarbonImmutable::now();
            $communication->metadata = $metadata !== null ? $this->redactor->redact($metadata) : null;
            $communication->save();

            $recipient = new CommunicationRecipient;
            $recipient->communication_id = $communication->id;
            $recipient->recipient_type = $fromType;
            $recipient->recipient_id = $fromId;
            $recipient->role = RecipientRole::Sender;
            $recipient->save();

            $content = new CommunicationContent;
            $content->communication_id = $communication->id;
            $content->channel = $channel;
            $content->subject = $this->redactor->redactText($subject);
            $content->content_text = $this->redactor->redactText($body);
            $content->rendered_at = CarbonImmutable::now();
            $content->save();

            Event::dispatch(new InboundCommunicationReceived(
                communicationId: $communication->id,
                threadId: $thread->id,
                channel: $channel,
            ));

            return $communication;
        });
    }

    private function validateMorph(string $name, string $type, string $id): void
    {
        if (mb_trim($type) === '' || mb_trim($id) === '') {
            throw new InvalidArgumentException("Inbound {$name} type and id must not be empty.");
        }

        if (! class_exists($type) && Relation::getMorphedModel($type) === null) {
            throw new InvalidArgumentException("Inbound {$name} type [{$type}] does not resolve to a model.");
        }
    }

    private function validateOptionalMorph(string $name, ?string $type, ?string $id): void
    {
        if ($type === null && $id === null) {
            return;
        }

        if ($type === null || $id === null) {
            throw new InvalidArgumentException("Inbound {$name} type and id must both be present or both be null.");
        }

        $this->validateMorph($name, $type, $id);
    }
}
