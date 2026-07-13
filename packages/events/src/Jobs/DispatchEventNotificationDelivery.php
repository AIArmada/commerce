<?php

declare(strict_types=1);

namespace AIArmada\Events\Jobs;

use AIArmada\Events\Models\EventNotificationDelivery;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Services\EventNotificationDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

final class DispatchEventNotificationDelivery implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries;

    public function __construct(public string $deliveryId)
    {
        $this->tries = max(1, (int) config('events.change_notices.delivery.max_attempts', 5));
    }

    public function uniqueId(): string
    {
        return $this->deliveryId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        $configured = config('events.change_notices.delivery.backoff_seconds', [10, 30, 120, 300]);

        return is_array($configured) ? array_values(array_map('intval', array_filter($configured, 'is_numeric'))) : [10, 30, 120, 300];
    }

    public function handle(EventNotificationDispatcher $dispatcher): void
    {
        $delivery = $this->claim();

        if (! $delivery instanceof EventNotificationDelivery) {
            $existing = EventNotificationDelivery::query()->find($this->deliveryId);

            if ($existing instanceof EventNotificationDelivery && $existing->status === 'dead') {
                $dispatcher->refreshBatch($existing->batch()->firstOrFail());
            }

            return;
        }

        try {
            if ($delivery->channel !== 'mail') {
                throw new RuntimeException('missing_adapter');
            }

            $recipient = $delivery->recipient()->first();
            $batch = $delivery->batch()->firstOrFail();
            $address = $this->mailAddress($recipient);

            if ($address === null) {
                throw new RuntimeException('invalid_recipient');
            }

            Mail::raw((string) ($batch->message ?: $batch->title), static function ($message) use ($address, $batch, $delivery): void {
                $message->to($address)->subject($batch->title);
                $message->getSymfonyMessage()->getHeaders()->addTextHeader('X-Event-Delivery-Id', $delivery->id);
            });

            $delivery->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                'leased_at' => null,
                'last_error_code' => null,
            ])->save();
            $dispatcher->refreshBatch($batch);
        } catch (Throwable $throwable) {
            $delivery->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'leased_at' => null,
                'last_error_code' => $this->safeCode($throwable),
            ])->save();
            $dispatcher->refreshBatch($delivery->batch()->firstOrFail());

            throw new RuntimeException('Event notification delivery failed.', previous: $throwable);
        }
    }

    public function failed(Throwable $throwable): void
    {
        $delivery = EventNotificationDelivery::query()->find($this->deliveryId);

        if (! $delivery instanceof EventNotificationDelivery || $delivery->status === 'sent') {
            return;
        }

        $delivery->forceFill([
            'status' => 'dead',
            'dead_at' => now(),
            'leased_at' => null,
            'last_error_code' => $delivery->last_error_code ?? $this->safeCode($throwable),
        ])->save();
        app(EventNotificationDispatcher::class)->refreshBatch($delivery->batch()->firstOrFail());
    }

    private function claim(): ?EventNotificationDelivery
    {
        return DB::transaction(function (): ?EventNotificationDelivery {
            $delivery = EventNotificationDelivery::query()->lockForUpdate()->find($this->deliveryId);

            if (! $delivery instanceof EventNotificationDelivery || in_array($delivery->status, ['sent', 'dead', 'cancelled'], true)) {
                return null;
            }

            $leaseSeconds = max(30, (int) config('events.change_notices.delivery.lease_seconds', 120));

            if ($delivery->status === 'processing' && $delivery->leased_at?->isAfter(now()->subSeconds($leaseSeconds))) {
                return null;
            }

            if ($delivery->attempt_count >= $delivery->max_attempts) {
                $delivery->forceFill(['status' => 'dead', 'dead_at' => now(), 'leased_at' => null])->save();

                return null;
            }

            $delivery->forceFill([
                'status' => 'processing',
                'attempt_count' => $delivery->attempt_count + 1,
                'last_attempt_at' => now(),
                'leased_at' => now(),
            ])->save();

            return $delivery;
        }, attempts: 3);
    }

    private function mailAddress(mixed $recipient): string | array | null
    {
        if ($recipient instanceof EventRegistration) {
            $participant = $recipient->resolvePrimaryParticipant();
            $email = $participant?->resolveEmail();
            $name = mb_trim((string) ($participant?->name ?? ''));

            return $email === null ? null : ($name === '' ? $email : [$email => $name]);
        }

        $email = $recipient instanceof Model ? $recipient->getAttribute('email') : null;

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    private function safeCode(Throwable $throwable): string
    {
        $message = mb_strtolower($throwable->getMessage());

        return match (true) {
            str_contains($message, 'missing_adapter') => 'missing_adapter',
            str_contains($message, 'invalid_recipient') => 'invalid_recipient',
            default => 'delivery_error',
        };
    }
}
