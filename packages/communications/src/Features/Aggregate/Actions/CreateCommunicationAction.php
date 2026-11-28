<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Aggregate\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\Communications\Contracts\CommunicationCreator;
use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Events\CommunicationCreated;
use AIArmada\Communications\Events\CommunicationQueued;
use AIArmada\Communications\Events\CommunicationScheduled;
use AIArmada\Communications\Features\Aggregate\Models\CommunicationBatch;
use AIArmada\Communications\Features\Aggregate\Models\CommunicationThread;
use AIArmada\Communications\Models\Communication;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use RuntimeException;

final readonly class CreateCommunicationAction implements CommunicationCreator
{
    public function __construct(
        private PayloadRedactor $redactor,
    ) {}

    public function handle(CommunicationContextData $context, bool $queued = false): string
    {
        $connection = (new Communication)->getConnection();

        return $connection->transaction(function () use ($context, $queued, $connection): string {
            $config = Communication::ownerScopeConfig();
            $owner = null;

            if ($config->enabled) {
                $owner = OwnerContext::resolveConfigured($config);
                OwnerContext::assertResolvedOrExplicitGlobal(
                    $owner,
                    'Creating a communication requires an owner context or explicit global context.',
                );
            }

            $expectedOwner = $config->enabled && $config->autoAssignOnCreate ? $owner : null;

            $this->assertReference(CommunicationBatch::class, $context->batchId, $config->enabled, $expectedOwner);
            $this->assertReference(CommunicationThread::class, $context->threadId, $config->enabled, $expectedOwner);
            $this->assertReference(Communication::class, $context->parentId, $config->enabled, $expectedOwner);

            $communication = new Communication;
            $communication->direction = CommunicationDirection::from($context->direction);
            $communication->category = CommunicationCategory::from($context->category);
            $communication->priority = CommunicationPriority::from($context->priority);
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

            if (! $communication->save()) {
                throw new RuntimeException('Communication creation was cancelled.');
            }

            $this->dispatchAfterCommit($connection, $owner, $config->enabled, new CommunicationCreated(
                communicationId: $communication->id,
                category: $communication->category->value,
                direction: $communication->direction->value,
                purpose: $communication->purpose,
            ));

            if ($communication->scheduled_at !== null) {
                $this->dispatchAfterCommit($connection, $owner, $config->enabled, new CommunicationScheduled(
                    communicationId: $communication->id,
                    scheduledAt: $communication->scheduled_at->toIso8601String(),
                ));
            }

            if ($queued) {
                $this->dispatchAfterCommit($connection, $owner, $config->enabled, new CommunicationQueued(communicationId: $communication->id));
            }

            return $communication->id;
        });
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function assertReference(
        string $modelClass,
        ?string $id,
        bool $ownerScoped,
        ?Model $expectedOwner,
    ): void {
        if ($id === null) {
            return;
        }

        if (mb_trim($id) === '') {
            throw new InvalidArgumentException('Communication reference IDs must not be empty.');
        }

        $record = $modelClass::query()->withoutGlobalScope(OwnerScope::class)->whereKey($id)->first();

        if (! $record instanceof Model) {
            throw (new ModelNotFoundException)->setModel($modelClass, $id);
        }

        if (! $ownerScoped) {
            return;
        }

        $recordOwnerType = $record->getAttribute('owner_type');
        $recordOwnerId = $record->getAttribute('owner_id');

        $matches = $expectedOwner === null
            ? $recordOwnerType === null && $recordOwnerId === null
            : $recordOwnerType === $expectedOwner->getMorphClass()
                && (string) $recordOwnerId === (string) $expectedOwner->getKey();

        if (! $matches) {
            throw new AuthorizationException(sprintf(
                'Referenced %s does not belong to the communication owner scope.',
                $modelClass,
            ));
        }
    }

    private function dispatchAfterCommit(
        Connection $connection,
        ?Model $owner,
        bool $ownerScoped,
        object $event,
    ): void {
        $connection->afterCommit(function () use ($owner, $ownerScoped, $event): void {
            if ($ownerScoped) {
                OwnerContext::withOwner($owner, fn () => Event::dispatch($event));

                return;
            }

            Event::dispatch($event);
        });
    }
}
