<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks;

use AIArmada\Chip\Actions\DispatchChipWebhookAction;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Models\Webhook;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class WebhookRetryManager
{
    /**
     * @var array<int, int> Backoff schedule in seconds: [attempt => delay]
     */
    protected array $backoffSchedule = [
        1 => 60,
        2 => 300,
        3 => 900,
        4 => 3600,
        5 => 14400,
    ];

    public function __construct(
        protected DispatchChipWebhookAction $dispatchAction,
    ) {}

    public function shouldRetry(Webhook $webhook): bool
    {
        if ($webhook->status !== 'failed') {
            return false;
        }

        return $webhook->retry_count < count($this->backoffSchedule);
    }

    public function getNextRetryDelay(Webhook $webhook): int
    {
        $nextAttempt = $webhook->retry_count + 1;

        return $this->backoffSchedule[$nextAttempt] ?? $this->backoffSchedule[5];
    }

    public function retry(Webhook $webhook): WebhookResult
    {
        $retryOwner = $this->resolveRetryOwner($webhook);

        $executeRetry = function () use ($webhook, $retryOwner): WebhookResult {
            $webhook->increment('retry_count');
            $webhook->update(['last_retry_at' => now()]);

            try {
                $payload = is_array($webhook->payload) ? $webhook->payload : [];
                $result = $this->dispatchAction->execute($webhook->event, $payload, $retryOwner ?? null);

                if ($result->isSuccess()) {
                    $webhook->update([
                        'status' => 'processed',
                        'processed' => true,
                        'processed_at' => now(),
                        'last_error' => null,
                    ]);
                } else {
                    $webhook->update([
                        'last_error' => $result->message,
                    ]);
                }

                return $result;

            } catch (Throwable $e) {
                $webhook->update([
                    'last_error' => $e->getMessage(),
                ]);

                return WebhookResult::failed($e->getMessage());
            }
        };

        return $retryOwner instanceof Model
            ? OwnerContext::withOwner($retryOwner, $executeRetry)
            : $executeRetry();
    }

    /**
     * @return Collection<int, Webhook>
     */
    public function getRetryableWebhooks(): Collection
    {
        $now = CarbonImmutable::now();

        return Webhook::query()
            ->forOwner()
            ->where('status', 'failed')
            ->where('retry_count', '<', count($this->backoffSchedule))
            ->get()
            ->filter(fn (Webhook $webhook): bool => $this->isEligibleForRetry($webhook, $now))
            ->values();
    }

    /**
     * @param  array<int, int>  $schedule
     */
    public function setBackoffSchedule(array $schedule): self
    {
        $this->backoffSchedule = $schedule;

        return $this;
    }

    private function isEligibleForRetry(Webhook $webhook, CarbonImmutable $now): bool
    {
        if ($webhook->last_retry_at === null) {
            return true;
        }

        $nextEligibleAt = CarbonImmutable::parse($webhook->last_retry_at)
            ->addSeconds($this->getNextRetryDelay($webhook));

        return $nextEligibleAt->lessThanOrEqualTo($now);
    }

    private function resolveRetryOwner(Webhook $webhook): ?Model
    {
        if (is_string($webhook->owner_type ?? null) && $webhook->owner_type !== '' && (is_string($webhook->owner_id ?? null) || is_int($webhook->owner_id ?? null))) {
            return OwnerContext::fromTypeAndId($webhook->owner_type, $webhook->owner_id);
        }

        $payload = is_array($webhook->payload) ? $webhook->payload : [];
        $payloadOwnerType = $payload['__owner_type'] ?? null;
        $payloadOwnerId = $payload['__owner_id'] ?? null;

        if (! is_string($payloadOwnerType) || $payloadOwnerType === '' || (! is_string($payloadOwnerId) && ! is_int($payloadOwnerId))) {
            return null;
        }

        return OwnerContext::fromTypeAndId($payloadOwnerType, $payloadOwnerId);
    }
}
