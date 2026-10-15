<?php

declare(strict_types=1);

namespace AIArmada\Addressing\ReferenceSync;

use JsonException;

/** Canonical, locale-independent serialization; excludes database UUIDs and write timestamps. */
final class Canonical
{
    /** @param array<string, mixed> $value */
    public static function json(array $value): string
    {
        return json_encode(self::sort($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    /** @param array<string, mixed> $value */
    public static function checksum(array $value): string
    {
        return hash('sha256', self::json($value));
    }

    public static function decode(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        try {
            $decoded = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('Invalid reference data JSON.', previous: $exception);
        }
        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('Expected JSON object/array.');
        }
        return $decoded;
    }

    private static function sort(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data, SORT_STRING);
        }
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::sort($value);
            }
        }
        return $data;
    }
}
