<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\RecipientRouting\Queries;

use AIArmada\Communications\Contracts\DestinationProtector;
use AIArmada\Communications\Contracts\DestinationResolver;
use AIArmada\Communications\Data\ResolvedDestinationData;
use AIArmada\Communications\Features\RecipientRouting\Models\CommunicationDestination;
use Illuminate\Notifications\Notification;
use TypeError;

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
                ->whereNotNull('address')
                ->orderByDesc('is_primary')
                ->orderByRaw('CASE WHEN verified_at IS NULL THEN 1 ELSE 0 END')
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
            try {
                $destination = $notifiable->routeNotificationFor($channel);
            } catch (TypeError) {
                $destination = $notifiable->routeNotificationFor($channel, $this->blankProbeNotification());
            }
        }

        if ($destination === null || $destination === '') {
            $driverMethod = 'routeNotificationFor' . ucfirst($channel);
            if (method_exists($notifiable, $driverMethod)) {
                try {
                    $destination = $notifiable->{$driverMethod}();
                } catch (TypeError) {
                    $destination = $notifiable->{$driverMethod}($this->blankProbeNotification());
                }
            }
        }

        if (is_array($destination)) {
            $firstKey = array_key_first($destination);

            if (is_string($firstKey) && mb_trim($firstKey) !== '') {
                $destination = $firstKey;
            } else {
                $firstValue = reset($destination);
                $destination = is_string($firstValue) && mb_trim($firstValue) !== '' ? $firstValue : null;
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

    private function blankProbeNotification(): Notification
    {
        return new class extends Notification {};
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
