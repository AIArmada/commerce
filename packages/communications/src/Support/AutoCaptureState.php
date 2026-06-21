<?php

declare(strict_types=1);

namespace AIArmada\Communications\Support;

final class AutoCaptureState
{
    /** @var array<int, array{communicationId: string, deliveryIdsByChannel: array<string, string>}> */
    private array $registry = [];

    private int $recursionDepth = 0;

    public function register(int $notificationHash, string $communicationId, array $deliveryIdsByChannel): void
    {
        $this->registry[$notificationHash] = [
            'communicationId' => $communicationId,
            'deliveryIdsByChannel' => $deliveryIdsByChannel,
        ];
    }

    public function addDelivery(int $notificationHash, string $channel, string $deliveryId): void
    {
        $this->registry[$notificationHash]['deliveryIdsByChannel'][$channel] = $deliveryId;
    }

    public function get(int $notificationHash): ?array
    {
        return $this->registry[$notificationHash] ?? null;
    }

    public function enterRecursion(): void
    {
        $this->recursionDepth++;
    }

    public function leaveRecursion(): void
    {
        $this->recursionDepth--;
    }

    public function isInsideRecursion(): bool
    {
        return $this->recursionDepth > 0;
    }
}
