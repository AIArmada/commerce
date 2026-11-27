<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\WebhookDelivery\Actions;

use AIArmada\Chip\Actions\DispatchChipWebhookAction;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Features\WebhookDelivery\Models\Webhook;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class WebhookRetryManager
{
    private const STALE_PROCESSING_MINUTES = 30;

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
        if ($webhook->status === 'processing' && $this->isStaleProcessingClaim($webhook)) {
            return $webhook->retry_count < count($this->backoffSchedule);
        }

        if ($webhook->status !== 'failed') {
            return false;
        }

        return $webhook->retry_count < count($this->backoffSchedule);
    }

    public function getNextRetryDelay(Webhook $webhook): int
    {
        $nextAttempt = $webhook->retry_count + 1;

        return $this->backoffSchedule[$nextAttempt] ?? (int) end($this->backoffSchedule);
    }

    public function retry(Webhook $webhook): WebhookResult
    {
        $retryOwner = OwnerContext::resolve();

        if ((bool) config('chip.owner.enabled', false)) {
            OwnerContext::assertResolvedOrExplicitGlobal($retryOwner);
        }

        $executeRetry = function () use ($webhook, $retryOwner): WebhookResult {
            $claimed = DB::transaction(function () use ($webhook): ?Webhook {
                $current = Webhook::query()->forOwner(OwnerContext::CURRENT, false)->lockForUpdate()->find($webhook->getKey());

                if (! $current instanceof Webhook || ! $this->shouldRetry($current) || ! $this->isEligibleForRetry($current, CarbonImmutable::now())) {
                    return null;
                }

                $current->forceFill([
                    'status' => 'processing',
                    'retry_count' => $current->retry_count + 1,
                    'last_retry_at' => CarbonImmutable::now(),
                ])->save();

                return $current;
            });

            if ($claimed === null) {
                return WebhookResult::skipped('Webhook is no longer eligible for retry.');
            }

            $webhook = $claimed;

            try {
                $payload = is_array($webhook->payload) ? $webhook->payload : [];
                $result = $this->dispatchAction->execute($webhook->event_type, $payload, $retryOwner ?? null);

                if ($result->isSuccess()) {
                    $webhook->forceFill([
                        'status' => 'processed',
                        'processed' => true,
                        'processed_at' => CarbonImmutable::now(),
                        'failed_at' => null,
                        'last_error' => null,
                    ])->save();
                } else {
                    $webhook->forceFill([
                        'status' => 'failed',
                        'failed_at' => CarbonImmutable::now(),
                        'last_error' => $result->message,
                    ])->save();
                }

                return $result;

            } catch (Throwable $e) {
                $webhook->forceFill([
                    'status' => 'failed',
                    'failed_at' => CarbonImmutable::now(),
                    'last_error' => $e->getMessage(),
                ])->save();

                return WebhookResult::failed($e->getMessage());
            }
        };

        return $executeRetry();
    }

    /**
     * @return Collection<int, Webhook>
     */
    public function getRetryableWebhooks(int $limit = 100): Collection
    {
        $now = CarbonImmutable::now();
        $maxAttempts = count($this->backoffSchedule);
        $backoffSchedule = $this->backoffSchedule;
        $staleCutoff = $now->subMinutes(self::STALE_PROCESSING_MINUTES);

        return Webhook::query()
            ->forOwner(OwnerContext::CURRENT, false)
            ->where('retry_count', '<', $maxAttempts)
            ->where(function ($query) use ($now, $maxAttempts, $backoffSchedule, $staleCutoff): void {
                $query->where('status', 'failed')
                    ->where(function ($query) use ($now, $maxAttempts, $backoffSchedule): void {
                        // Never retried: eligible immediately.
                        $query->whereNull('last_retry_at');

                        // Otherwise the backoff delay depends on the attempt count,
                        // so compare last_retry_at against one cutoff per count.
                        for ($retryCount = 0; $retryCount < $maxAttempts; $retryCount++) {
                            $delay = $backoffSchedule[$retryCount + 1] ?? end($backoffSchedule);
                            $cutoff = $now->subSeconds(max(0, (int) $delay));

                            $query->orWhere(function ($query) use ($retryCount, $cutoff): void {
                                $query->where('retry_count', $retryCount)
                                    ->where('last_retry_at', '<=', $cutoff);
                            });
                        }
                    });

                $query->orWhere(function ($query) use ($staleCutoff): void {
                    $query->where('status', 'processing')
                        ->where('updated_at', '<=', $staleCutoff);
                });
            })
            ->orderBy('id')
            ->limit(max(0, $limit))
            ->get();
    }

    /**
     * @param  array<int, int>  $schedule
     */
    public function setBackoffSchedule(array $schedule): self
    {
        if ($schedule === [] || array_keys($schedule) !== range(1, count($schedule)) || min($schedule) < 0) {
            throw new InvalidArgumentException('Webhook retry backoff must contain consecutive attempts with non-negative delays.');
        }

        $this->backoffSchedule = $schedule;

        return $this;
    }

    private function isStaleProcessingClaim(Webhook $webhook): bool
    {
        $updatedAt = $webhook->getAttribute('updated_at');

        if (! $updatedAt instanceof CarbonImmutable) {
            $updatedAt = $updatedAt === null ? null : CarbonImmutable::parse($updatedAt);
        }

        return $updatedAt instanceof CarbonImmutable
            && $updatedAt->lessThan(CarbonImmutable::now()->subMinutes(self::STALE_PROCESSING_MINUTES));
    }

    private function isEligibleForRetry(Webhook $webhook, CarbonImmutable $now): bool
    {
        if ($webhook->status === 'processing') {
            return $this->isStaleProcessingClaim($webhook);
        }

        if ($webhook->last_retry_at === null) {
            return true;
        }

        $nextEligibleAt = CarbonImmutable::parse($webhook->last_retry_at)
            ->addSeconds($this->getNextRetryDelay($webhook));

        return $nextEligibleAt->lessThanOrEqualTo($now);
    }
}
