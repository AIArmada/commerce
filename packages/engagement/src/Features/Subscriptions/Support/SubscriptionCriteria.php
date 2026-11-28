<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Subscriptions\Support;

use InvalidArgumentException;

final class SubscriptionCriteria
{
    /**
     * @param  array<string|int, mixed>  $data
     * @return array<string|int, mixed>
     */
    public static function normalize(array $data, int $depth = 0): array
    {
        if ($depth > 32) {
            throw new InvalidArgumentException('Subscription criteria are nested too deeply.');
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::normalize($value, $depth + 1);
            } elseif (! is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException('Subscription criteria must contain only JSON scalar values and arrays.');
            }
        }

        if ($data !== [] && array_keys($data) !== range(0, count($data) - 1)) {
            ksort($data);
        }

        return $data;
    }

    /**
     * Stable identity hash for normalized criteria. Used for indexed equality
     * lookups; callers still compare the decoded criteria in PHP so a hash
     * collision can never return the wrong subscription.
     *
     * @param  array<string|int, mixed>|null  $criteria
     */
    public static function hash(?array $criteria): string
    {
        return hash('sha256', json_encode(self::normalize($criteria ?? []), JSON_THROW_ON_ERROR));
    }
}
