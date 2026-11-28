<?php

declare(strict_types=1);

namespace AIArmada\Communications\Support;

use WeakMap;

final class AutoCaptureState
{
    /** @var WeakMap<object, array<string, array{communicationId: string, deliveryIdsByChannel: array<string, string>}>> */
    private readonly WeakMap $registry;

    private int $recursionDepth = 0;

    public function __construct()
    {
        $this->registry = new WeakMap;
    }

    /** @param array<string, string> $deliveryIdsByChannel */
    public function register(object $notification, mixed $notifiable, string $communicationId, array $deliveryIdsByChannel): void
    {
        $states = $this->registry[$notification] ?? [];
        $states[$this->recipientKey($notifiable)] = [
            'communicationId' => $communicationId,
            'deliveryIdsByChannel' => $deliveryIdsByChannel,
        ];
        $this->registry[$notification] = $states;
    }

    public function addDelivery(object $notification, mixed $notifiable, string $channel, string $deliveryId): void
    {
        $states = $this->registry[$notification] ?? [];
        $key = $this->recipientKey($notifiable);

        if (! isset($states[$key])) {
            return;
        }

        $states[$key]['deliveryIdsByChannel'][$channel] = $deliveryId;
        $this->registry[$notification] = $states;
    }

    /** @return array{communicationId: string, deliveryIdsByChannel: array<string, string>}|null */
    public function get(object $notification, mixed $notifiable): ?array
    {
        return ($this->registry[$notification] ?? [])[$this->recipientKey($notifiable)] ?? null;
    }

    public function removeDelivery(object $notification, mixed $notifiable, string $channel): void
    {
        $states = $this->registry[$notification] ?? [];
        $key = $this->recipientKey($notifiable);

        if (! isset($states[$key])) {
            return;
        }

        unset($states[$key]['deliveryIdsByChannel'][$channel]);

        if ($states[$key]['deliveryIdsByChannel'] === []) {
            unset($states[$key]);
        }

        if ($states === []) {
            unset($this->registry[$notification]);

            return;
        }

        $this->registry[$notification] = $states;
    }

    private function recipientKey(mixed $notifiable): string
    {
        if (is_object($notifiable)) {
            if (method_exists($notifiable, 'getKey') && $notifiable->getKey() !== null) {
                $type = method_exists($notifiable, 'getMorphClass') ? $notifiable->getMorphClass() : $notifiable::class;

                return hash('sha256', json_encode([$type, (string) $notifiable->getKey()], JSON_THROW_ON_ERROR));
            }

            return 'object:' . spl_object_id($notifiable);
        }

        return hash('sha256', serialize($notifiable));
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
