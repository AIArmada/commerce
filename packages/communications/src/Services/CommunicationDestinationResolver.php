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

        if (! method_exists($notifiable, 'getMorphClass') || ! method_exists($notifiable, 'getKey')) {
            return null;
        }

        $destination = CommunicationDestination::query()
            ->where('recipient_type', $notifiable->getMorphClass())
            ->where('recipient_id', $notifiable->getKey())
            ->where('channel', $channel)
            ->where('status', 'active')
            ->orderByDesc('is_primary')
            ->orderByDesc('verified_at')
            ->first();

        if (! $destination || ! $destination->address) {
            return null;
        }

        return new ResolvedDestinationData(
            destination: $destination->address,
            channel: $channel,
            ciphertext: $this->protector->encrypt($destination->address),
            hash: $this->protector->hash($destination->address),
            hint: $this->protector->hint($destination->address),
        );
    }
}
