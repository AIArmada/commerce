<?php

declare(strict_types=1);

namespace AIArmada\Communications\Services;

use AIArmada\Communications\Contracts\DestinationProtector;
use AIArmada\Communications\Contracts\DestinationResolver;
use AIArmada\Communications\Data\ResolvedDestinationData;

final class NotifiableDestinationResolver implements DestinationResolver
{
    public function __construct(
        private readonly DestinationProtector $protector,
    ) {}

    public function resolve(mixed $notifiable, string $channel): ?ResolvedDestinationData
    {
        if (! is_object($notifiable)) {
            return null;
        }

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

        if ($destination === null) {
            return null;
        }

        return new ResolvedDestinationData(
            destination: $destination,
            channel: $channel,
            ciphertext: $this->protector->encrypt($destination),
            hash: $this->protector->hash($destination),
            hint: $this->protector->hint($destination),
        );
    }
}
