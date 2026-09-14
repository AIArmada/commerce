<?php

declare(strict_types=1);

namespace AIArmada\Communications\Support;

use SplObjectStorage;

final class AutoCaptureState
{
    /** @var SplObjectStorage<object, array{communicationId: string, deliveryIdsByChannel: array<string, string>}> */
    private readonly SplObjectStorage $registry;

    private int $recursionDepth = 0;

    public function __construct()
    {
        $this->registry = new SplObjectStorage;
    }

    /**
     * @param  array<string, string>  $deliveryIdsByChannel
     */
    public function register(object $notification, string $communicationId, array $deliveryIdsByChannel): void
    {
        $this->registry[$notification] = [
            'communicationId' => $communicationId,
            'deliveryIdsByChannel' => $deliveryIdsByChannel,
        ];
    }

    public function addDelivery(object $notification, string $channel, string $deliveryId): void
    {
        if (! isset($this->registry[$notification])) {
            return;
        }

        $state = $this->registry[$notification];
        $state['deliveryIdsByChannel'][$channel] = $deliveryId;
        $this->registry[$notification] = $state;
    }

    public function get(object $notification): ?array
    {
        return $this->registry[$notification] ?? null;
    }

    public function removeDelivery(object $notification, string $channel): void
    {
        if (! isset($this->registry[$notification])) {
            return;
        }

        $state = $this->registry[$notification];
        unset($state['deliveryIdsByChannel'][$channel]);

        if ($state['deliveryIdsByChannel'] === []) {
            unset($this->registry[$notification]);

            return;
        }

        $this->registry[$notification] = $state;
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
