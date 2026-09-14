<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Support;

use JsonException;

final class SubscriptionCriteria
{
    /**
     * @param  array<string|int, mixed>  $data
     * @return array<string|int, mixed>
     */
    public static function normalize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::normalize($value);
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
        try {
            $payload = json_encode(self::normalize($criteria ?? []), JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $payload = '[]';
        }

        return hash('sha256', $payload === false ? '[]' : $payload);
    }
}
