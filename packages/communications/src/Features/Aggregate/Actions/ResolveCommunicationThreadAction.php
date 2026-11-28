<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Aggregate\Actions;

use AIArmada\CommerceSupport\Data\OwnerScopeConfig;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Enums\ThreadStatus;
use AIArmada\Communications\Features\Aggregate\Models\CommunicationThread;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;

final class ResolveCommunicationThreadAction
{
    public function __construct(
        private readonly PayloadRedactor $redactor,
    ) {}

    public function handle(
        string $channel,
        ?string $externalThreadId = null,
        ?string $title = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?array $metadata = null,
    ): CommunicationThread {
        $config = CommunicationThread::ownerScopeConfig();
        $owner = null;

        if ($config->enabled) {
            $owner = OwnerContext::resolveConfigured($config);
            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                'Resolving a communication thread requires an owner context or explicit global context.',
            );
        }

        $writeOwner = $config->autoAssignOnCreate ? $owner : null;

        if ($externalThreadId !== null) {
            $thread = $this->partitionQuery($config, $writeOwner)
                ->where('channel', $channel)
                ->where('external_thread_id', $externalThreadId)
                ->first();

            if ($thread instanceof CommunicationThread) {
                return $this->touch($thread, $title);
            }

            try {
                return $this->create($channel, $externalThreadId, $title, $subjectType, $subjectId, $metadata);
            } catch (UniqueConstraintViolationException $exception) {
                $winner = $this->partitionQuery($config, $writeOwner)
                    ->where('channel', $channel)
                    ->where('external_thread_id', $externalThreadId)
                    ->first();

                if (! $winner instanceof CommunicationThread) {
                    throw $exception;
                }

                return $this->touch($winner, $title);
            }
        }

        if ($subjectType !== null && $subjectId !== null) {
            $thread = $this->partitionQuery($config, $writeOwner)
                ->where('channel', $channel)
                ->where('subject_type', $subjectType)
                ->where('subject_id', $subjectId)
                ->whereNull('external_thread_id')
                ->first();

            if ($thread instanceof CommunicationThread) {
                return $this->touch($thread, null);
            }
        }

        return $this->create($channel, null, $title, $subjectType, $subjectId, $metadata);
    }

    /**
     * @return Builder<CommunicationThread>
     */
    private function partitionQuery(OwnerScopeConfig $config, ?Model $writeOwner): Builder
    {
        return OwnerQuery::applyToEloquentBuilder(
            CommunicationThread::query()->withoutOwnerScope(),
            $writeOwner,
            false,
            $config->ownerTypeColumn,
            $config->ownerIdColumn,
        );
    }

    private function touch(CommunicationThread $thread, ?string $title): CommunicationThread
    {
        if ($title !== null) {
            $thread->title = $title;
        }

        $thread->last_communication_at = CarbonImmutable::now();
        $thread->save();

        return $thread;
    }

    private function create(
        string $channel,
        ?string $externalThreadId,
        ?string $title,
        ?string $subjectType,
        ?string $subjectId,
        ?array $metadata,
    ): CommunicationThread {
        $thread = new CommunicationThread;
        $thread->channel = $channel;
        $thread->external_thread_id = $externalThreadId;
        $thread->title = $title;
        $thread->subject_type = $subjectType;
        $thread->subject_id = $subjectId;
        $thread->status = ThreadStatus::Open;
        $thread->opened_at = CarbonImmutable::now();
        $thread->last_communication_at = CarbonImmutable::now();
        $thread->metadata = $metadata !== null ? $this->redactor->redact($metadata) : null;

        return $thread->getConnection()->transaction(function () use ($thread): CommunicationThread {
            if (! $thread->save()) {
                throw new RuntimeException('Communication thread creation was cancelled.');
            }

            return $thread;
        });
    }
}
