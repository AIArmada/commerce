<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\Events\Contracts\EventChangeNoticeAudienceResolver;
use AIArmada\Events\Contracts\EventChangeNoticeNotificationDispatcher;
use AIArmada\Events\Jobs\DispatchEventNotificationDelivery;
use AIArmada\Events\Models\EventNotificationBatch;
use AIArmada\Events\Models\EventNotificationDelivery;
use AIArmada\Events\Models\EventRegistration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EventNotificationDispatcher implements EventChangeNoticeNotificationDispatcher
{
    public function __construct(private readonly EventChangeNoticeAudienceResolver $audienceResolver) {}

    /** @param array<string, mixed> $attributes */
    public function createBatch(array $attributes): EventNotificationBatch
    {
        return EventNotificationBatch::query()->create([
            'event_id' => $attributes['event_id'],
            'title' => $attributes['title'],
            'message' => $attributes['message'] ?? null,
            'audience_scope' => $attributes['audience_scope'],
            'channels' => $attributes['channels'] ?? null,
            'status' => 'pending',
            'scheduled_at' => $attributes['scheduled_at'] ?? null,
            'created_by_type' => $attributes['created_by_type'] ?? null,
            'created_by_id' => $attributes['created_by_id'] ?? null,
            'metadata' => $attributes['metadata'] ?? null,
        ]);
    }

    public function cancel(EventNotificationBatch $batch): void
    {
        DB::transaction(function () use ($batch): void {
            $locked = EventNotificationBatch::query()->lockForUpdate()->find($batch->id);

            if (! $locked instanceof EventNotificationBatch || in_array($locked->status, ['sent', 'cancelled'], true)) {
                return;
            }

            $locked->deliveries()
                ->whereNotIn('status', ['sent', 'dead'])
                ->update(['status' => 'cancelled', 'leased_at' => null, 'updated_at' => now()]);

            $locked->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();
        }, attempts: 3);
    }

    public function dispatch(EventNotificationBatch $batch): void
    {
        $deliveryIds = DB::transaction(function () use ($batch): array {
            $locked = EventNotificationBatch::query()->with(['eventUpdate', 'deliveries'])->lockForUpdate()->find($batch->id);

            if (! $locked instanceof EventNotificationBatch || in_array($locked->status, ['sent', 'cancelled'], true)) {
                return [];
            }

            if ($locked->deliveries->isNotEmpty()) {
                $retryable = $locked->deliveries->whereNotIn('status', ['sent', 'cancelled']);

                foreach ($retryable as $delivery) {
                    if ($delivery->status === 'dead') {
                        $delivery->forceFill([
                            'status' => 'pending',
                            'attempt_count' => 0,
                            'leased_at' => null,
                            'failed_at' => null,
                            'dead_at' => null,
                            'last_error_code' => null,
                        ])->save();
                    }
                }

                if ($retryable->isNotEmpty()) {
                    $locked->forceFill(['status' => 'processing', 'sent_at' => null])->save();
                }

                return $retryable->pluck('id')->unique()->values()->all();
            }

            $channels = $locked->channels ?: config('events.change_notices.channels', ['mail']);
            $channels = array_values(array_unique(array_filter((array) $channels, 'is_string')));

            if ($channels === [] || array_diff($channels, ['mail']) !== []) {
                $locked->forceFill([
                    'status' => 'failed',
                    'metadata' => array_merge($locked->metadata ?? [], ['failure_code' => 'MISSING_NOTIFICATION_ADAPTER']),
                ])->save();

                return [];
            }

            $recipients = collect();

            if ($locked->eventUpdate !== null) {
                $recipients = collect($this->audienceResolver->resolve($locked->eventUpdate, (string) $locked->audience_scope));
            }

            if ($recipients->isEmpty() && in_array($locked->audience_scope, ['registrants', 'all'], true)) {
                $recipients = EventRegistration::query()
                    ->where('event_id', $locked->event_id)
                    ->whereIn('status', EventRegistration::CAPACITY_BLOCKING_STATUSES)
                    ->get();
            }

            $models = $recipients->filter(static fn (mixed $recipient): bool => $recipient instanceof Model && Str::isUuid((string) $recipient->getKey()));
            $ids = [];

            foreach ($models as $recipient) {
                foreach ($channels as $channel) {
                    $delivery = EventNotificationDelivery::query()->firstOrCreate([
                        'event_notification_batch_id' => $locked->id,
                        'recipient_type' => $recipient->getMorphClass(),
                        'recipient_id' => $recipient->getKey(),
                        'channel' => $channel,
                    ], [
                        'status' => 'pending',
                        'max_attempts' => max(1, (int) config('events.change_notices.delivery.max_attempts', 5)),
                    ]);
                    $ids[] = $delivery->id;
                }
            }

            if ($ids === []) {
                $locked->forceFill([
                    'status' => 'failed',
                    'metadata' => array_merge($locked->metadata ?? [], ['failure_code' => 'NO_DELIVERABLE_RECIPIENTS']),
                ])->save();

                return [];
            }

            $locked->forceFill(['status' => 'processing'])->save();

            return array_values(array_unique($ids));
        }, attempts: 3);

        foreach ($deliveryIds as $deliveryId) {
            DispatchEventNotificationDelivery::dispatch((string) $deliveryId)->afterCommit();
        }
    }

    public function refreshBatch(EventNotificationBatch $batch): void
    {
        $statuses = $batch->deliveries()->pluck('status');

        if ($statuses->isEmpty() || $batch->status === 'cancelled') {
            return;
        }

        $sent = $statuses->filter(static fn (string $status): bool => $status === 'sent')->count();
        $terminalFailures = $statuses->filter(static fn (string $status): bool => $status === 'dead')->count();
        $total = $statuses->count();

        $status = match (true) {
            $sent === $total => 'sent',
            $terminalFailures === $total => 'failed',
            $sent > 0 && $sent + $terminalFailures === $total => 'partial',
            default => 'processing',
        };

        $batch->forceFill([
            'status' => $status,
            'sent_at' => $status === 'sent' ? now() : null,
        ])->save();
    }
}
