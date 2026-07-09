<?php

declare(strict_types=1);

namespace AIArmada\Communications\Services;

use AIArmada\Communications\Contracts\DestinationProtector;
use AIArmada\Communications\Contracts\DestinationResolver;
use AIArmada\Communications\Data\ResolvedDestinationData;
use AIArmada\Communications\Models\CommunicationDestination;

final class CommunicationDestinationResolver implements DestinationResolver
{
    public function __construct(
        private readonly DestinationProtector $protector,
    ) {}

    public function resolve(mixed $notifiable, string $channel): ?ResolvedDestinationData
    {
        if (! is_object($notifiable)) {
            return null;
        }

        if (method_exists($notifiable, 'getMorphClass') && method_exists($notifiable, 'getKey')) {
            $destination = CommunicationDestination::query()
                ->where('recipient_type', $notifiable->getMorphClass())
                ->where('recipient_id', $notifiable->getKey())
                ->where('channel', $channel)
                ->where('status', 'active')
                ->orderByDesc('is_primary')
                ->orderByDesc('verified_at')
                ->first();

            if ($destination && $destination->address) {
                return $this->buildResult($destination->address, $channel);
            }
        }

        $address = $this->resolveFromNotifiable($notifiable, $channel);

        if ($address === null) {
            return null;
        }

        return $this->buildResult($address, $channel);
    }

    private function resolveFromNotifiable(mixed $notifiable, string $channel): ?string
    {
        $destination = null;

        if (method_exists($notifiable, 'routeNotificationFor')) {
            $destination = $notifiable->routeNotificationFor($channel);
        }

        if ($destination === null || $destination === '') {
            $driverMethod = 'routeNotificationFor' . ucfirst($channel);
            if (method_exists($notifiable, $driverMethod)) {
                $destination = $notifiable->{$driverMethod}();
            }
        }

        if ($destination === null || $destination === '') {
            return null;
        }

        if (! is_string($destination)) {
            $destination = method_exists($notifiable, 'getRouteKey')
                ? (string) $notifiable->getRouteKey()
                : null;
        }

        return $destination;
    }

    private function buildResult(string $destination, string $channel): ResolvedDestinationData
    {
        return new ResolvedDestinationData(
            destination: $destination,
            channel: $channel,
            ciphertext: $this->protector->encrypt($destination),
            hash: $this->protector->hash($destination),
            hint: $this->protector->hint($destination),
        );
    }
}
