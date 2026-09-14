<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Enums\ThreadStatus;
use AIArmada\Communications\Models\CommunicationThread;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

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
        if ($externalThreadId !== null) {
            $thread = CommunicationThread::query()
                ->where('channel', $channel)
                ->where('external_thread_id', $externalThreadId)
                ->first();

            if ($thread !== null) {
                return $this->touch($thread, $title);
            }

            try {
                return $this->create($channel, $externalThreadId, $title, $subjectType, $subjectId, $metadata);
            } catch (QueryException $exception) {
                if ($exception->getCode() !== '23000') {
                    throw $exception;
                }

                $winner = CommunicationThread::query()
                    ->where('channel', $channel)
                    ->where('external_thread_id', $externalThreadId)
                    ->firstOrFail();

                return $this->touch($winner, $title);
            }
        }

        if ($subjectType !== null && $subjectId !== null) {
            $thread = CommunicationThread::query()
                ->where('channel', $channel)
                ->where('subject_type', $subjectType)
                ->where('subject_id', $subjectId)
                ->whereNull('external_thread_id')
                ->first();

            if ($thread !== null) {
                return $this->touch($thread, null);
            }
        }

        return $this->create($channel, null, $title, $subjectType, $subjectId, $metadata);
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
        $thread->save();

        return $thread;
    }
}
