<?php

declare(strict_types=1);

namespace AIArmada\Communications\Traits;

use AIArmada\Communications\Contracts\CommunicationRecorder;
use Throwable;

trait HasCommunicationContext
{
    public ?string $communicationId = null;

    /** @var array<string, string> */
    public array $deliveryIdsByChannel = [];

    public ?string $ownerType = null;

    public ?string $ownerId = null;

    public function withCommunicationContext(
        string $communicationId,
        array $deliveryIdsByChannel,
        ?string $ownerType = null,
        ?string $ownerId = null,
    ): static {
        $this->communicationId = $communicationId;
        $this->deliveryIdsByChannel = $deliveryIdsByChannel;
        $this->ownerType = $ownerType;
        $this->ownerId = $ownerId;

        return $this;
    }

    public function communicationId(): ?string
    {
        return $this->communicationId;
    }

    public function deliveryIdForChannel(string $channel): ?string
    {
        return $this->deliveryIdsByChannel[$channel] ?? null;
    }

    public function recordCommunicationFailure(Throwable $exception): void
    {
        if ($this->communicationId === null) {
            return;
        }

        try {
            $recorder = app(CommunicationRecorder::class);

            foreach ($this->deliveryIdsByChannel as $deliveryId) {
                $recorder->markFailed(
                    $this->communicationId,
                    $deliveryId,
                    $exception->getMessage(),
                );
            }
        } catch (Throwable) {
            // Silently handle - we're in a failure handler
        }
    }
}
