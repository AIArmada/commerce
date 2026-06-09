<?php

declare(strict_types=1);

namespace AIArmada\Chip\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;

final class ChipOwnerTuple
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{string, string}|null
     */
    public static function extractFromPayload(array $payload): ?array
    {
        $ownerType = $payload['__owner_type'] ?? null;
        $ownerId = $payload['__owner_id'] ?? null;

        if (! is_string($ownerType)) {
            return null;
        }

        if (! is_string($ownerId) && ! is_int($ownerId)) {
            return null;
        }

        return [$ownerType, (string) $ownerId];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function resolveFromPayload(array $payload): ?Model
    {
        $tuple = self::extractFromPayload($payload);

        if ($tuple === null) {
            return null;
        }

        return OwnerContext::fromTypeAndId($tuple[0], $tuple[1]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function embedInPayload(array $payload, Model $owner): array
    {
        $payload['__owner_type'] = $owner->getMorphClass();
        $payload['__owner_id'] = (string) $owner->getKey();

        return $payload;
    }
}
