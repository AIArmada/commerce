<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Webhooks\EntryPoints\Jobs;

use AIArmada\Affiliates\Features\Webhooks\Actions\DeliverAffiliateWebhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

final class DispatchAffiliateWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries;

    public function __construct(public string $deliveryId)
    {
        $this->tries = max(1, (int) config('affiliates.webhooks.delivery.max_attempts', 5));
    }

    /** @return list<int> */
    public function backoff(): array
    {
        $configured = config('affiliates.webhooks.delivery.backoff_seconds', [10, 30, 120, 300]);

        return is_array($configured) ? array_values(array_map(static fn ($value): int => max(0, (int) $value), array_filter($configured, 'is_numeric'))) : [10, 30, 120, 300];
    }

    public function handle(DeliverAffiliateWebhook $deliver): void
    {
        $deliver->execute($this->deliveryId);
    }

    public function failed(Throwable $throwable): void
    {
        app(DeliverAffiliateWebhook::class)->markDead($this->deliveryId);
    }
}
