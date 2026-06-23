<?php

declare(strict_types=1);

namespace AIArmada\Communications\Jobs;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Actions\ApplyProviderEventAction;
use AIArmada\Communications\Contracts\IdempotencyLock;
use AIArmada\Communications\Webhooks\Contracts\ProviderEventNormalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessWebhookEventJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 60;

    public function __construct(
        public readonly string $provider,
        public readonly array $payload,
        public readonly ?string $ownerId = null,
        public readonly ?string $ownerType = null,
    ) {}

    public function uniqueId(): string
    {
        return $this->fingerprint();
    }

    public function handle(
        ProviderEventNormalizer $normalizer,
        ApplyProviderEventAction $applyAction,
        IdempotencyLock $lock,
    ): void {
        $fingerprint = $this->fingerprint();

        if ($lock->exists($fingerprint)) {
            return;
        }

        $lock->acquire($fingerprint, 3600);

        try {
            OwnerContext::withOwner(
                OwnerContext::fromTypeAndId($this->ownerType, $this->ownerId),
                function () use ($normalizer, $applyAction): void {
                    $eventData = $normalizer->normalize($this->provider, $this->payload);
                    $applyAction->handle($eventData);
                },
            );
        } finally {
            $lock->release($fingerprint);
        }
    }

    private function fingerprint(): string
    {
        return 'webhook:' . md5(json_encode([
            'provider' => $this->provider,
            'ownerType' => $this->ownerType,
            'ownerId' => $this->ownerId,
            'payload' => $this->payload,
        ]));
    }
}
