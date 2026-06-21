<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Enums\ThreadStatus;
use AIArmada\Communications\Models\CommunicationThread;
use Carbon\CarbonImmutable;

final class ResolveCommunicationThreadAction
{
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
                if ($title !== null) {
                    $thread->title = $title;
                }
                $thread->last_communication_at = CarbonImmutable::now();
                $thread->save();

                return $thread;
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
                $thread->last_communication_at = CarbonImmutable::now();
                $thread->save();

                return $thread;
            }
        }

        $thread = new CommunicationThread;
        $thread->channel = $channel;
        $thread->external_thread_id = $externalThreadId;
        $thread->title = $title;
        $thread->subject_type = $subjectType;
        $thread->subject_id = $subjectId;
        $thread->status = ThreadStatus::Open;
        $thread->opened_at = CarbonImmutable::now();
        $thread->last_communication_at = CarbonImmutable::now();
        $thread->metadata = $metadata;
        $thread->save();

        return $thread;
    }
}
