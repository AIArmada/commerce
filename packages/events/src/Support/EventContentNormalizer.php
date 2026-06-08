<?php

declare(strict_types=1);

namespace AIArmada\Events\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class EventContentNormalizer
{
    /**
     * @param  array<int, mixed>|array<string, mixed>  $taxonomy
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeClassifications(array $taxonomy): array
    {
        $assignments = [];

        foreach ($taxonomy as $groupKey => $terms) {
            $groupKey = self::stringOrNull($groupKey) ?? 'default';

            if (! is_array($terms)) {
                $terms = [$terms];
            }

            foreach (array_values($terms) as $index => $term) {
                $normalized = self::normalizeClassificationTerm($groupKey, $term, $index + 1);

                if ($normalized !== null) {
                    $assignments[] = $normalized;
                }
            }
        }

        return $assignments;
    }

    /**
     * @param  array<int, mixed>|array<string, mixed>  $mediaReferences
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeAssets(array $mediaReferences): array
    {
        $assignments = [];

        foreach ($mediaReferences as $roleKey => $references) {
            $roleKey = self::stringOrNull($roleKey) ?? 'default';

            if (! is_array($references)) {
                $references = [$references];
            }

            if (Arr::isList($references)) {
                foreach ($references as $index => $reference) {
                    $normalized = self::normalizeAssetReference($roleKey, $reference, $index + 1);

                    if ($normalized !== null) {
                        $assignments[] = $normalized;
                    }
                }

                continue;
            }

            $normalized = self::normalizeAssetReference($roleKey, $references, 1);

            if ($normalized !== null) {
                $assignments[] = $normalized;
            }
        }

        return $assignments;
    }

    /**
     * @param  array<int, mixed>|array<string, mixed>  $references
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeReferences(array $references): array
    {
        $assignments = [];

        foreach ($references as $groupKey => $value) {
            $referenceKind = self::stringOrNull($groupKey);

            if (! is_array($value)) {
                $value = [$value];
            }

            if (Arr::isList($value)) {
                foreach ($value as $index => $reference) {
                    $normalized = self::normalizeReference($referenceKind, $reference, $index + 1);

                    if ($normalized !== null) {
                        $assignments[] = $normalized;
                    }
                }

                continue;
            }

            $normalized = self::normalizeReference($referenceKind, $value, 1);

            if ($normalized !== null) {
                $assignments[] = $normalized;
            }
        }

        return $assignments;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function normalizeClassificationTerm(string $groupKey, mixed $term, int $orderColumn): ?array
    {
        if (is_string($term)) {
            $termKey = self::stringOrNull($term);

            if ($termKey === null) {
                return null;
            }

            return [
                'group_key' => $groupKey,
                'term_key' => $termKey,
                'term_label' => Str::headline(str_replace(['-', '_'], ' ', $termKey)),
                'order_column' => $orderColumn,
                'metadata' => null,
            ];
        }

        if (! is_array($term)) {
            return null;
        }

        $termKey = self::stringOrNull($term['term_key'] ?? $term['key'] ?? $term['slug'] ?? null);

        if ($termKey === null) {
            return null;
        }

        return [
            'group_key' => $groupKey,
            'term_key' => $termKey,
            'term_label' => self::stringOrNull($term['term_label'] ?? $term['label'] ?? $term['name'] ?? null)
                ?? Str::headline(str_replace(['-', '_'], ' ', $termKey)),
            'source_type' => self::stringOrNull($term['source_type'] ?? null),
            'source_id' => self::stringOrNull($term['source_id'] ?? null),
            'order_column' => $orderColumn,
            'metadata' => self::arrayOrNull($term['metadata'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function normalizeAssetReference(string $roleKey, mixed $reference, int $orderColumn): ?array
    {
        if (is_string($reference)) {
            $url = self::stringOrNull($reference);

            if ($url === null) {
                return null;
            }

            return [
                'role_key' => $roleKey,
                'provider' => null,
                'provider_reference' => null,
                'url' => $url,
                'title' => null,
                'alt_text' => null,
                'visibility' => 'public',
                'order_column' => $orderColumn,
                'metadata' => null,
            ];
        }

        if (! is_array($reference)) {
            return null;
        }

        $url = self::stringOrNull($reference['url'] ?? $reference['href'] ?? $reference['source'] ?? null);

        if ($url === null && self::stringOrNull($reference['provider_reference'] ?? null) === null) {
            return null;
        }

        return [
            'role_key' => $roleKey,
            'provider' => self::stringOrNull($reference['provider'] ?? null),
            'provider_reference' => self::stringOrNull($reference['provider_reference'] ?? $reference['reference'] ?? null),
            'url' => $url,
            'title' => self::stringOrNull($reference['title'] ?? $reference['label'] ?? null),
            'alt_text' => self::stringOrNull($reference['alt_text'] ?? $reference['alt'] ?? null),
            'visibility' => self::stringOrNull($reference['visibility'] ?? null) ?? 'public',
            'order_column' => $orderColumn,
            'metadata' => self::arrayOrNull($reference['metadata'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function normalizeReference(?string $referenceKind, mixed $reference, int $orderColumn): ?array
    {
        if (is_string($reference)) {
            $url = self::stringOrNull($reference);

            if ($url === null) {
                return null;
            }

            return [
                'reference_kind' => $referenceKind ?? 'source_material',
                'reference_type' => null,
                'reference_id' => null,
                'display_label' => null,
                'source_label' => null,
                'url' => $url,
                'order_column' => $orderColumn,
                'metadata' => null,
            ];
        }

        if (! is_array($reference)) {
            return null;
        }

        $kind = self::stringOrNull($reference['reference_kind'] ?? $reference['kind'] ?? null)
            ?? $referenceKind
            ?? 'source_material';
        $url = self::stringOrNull($reference['url'] ?? $reference['href'] ?? $reference['source'] ?? null);
        $displayLabel = self::stringOrNull($reference['display_label'] ?? $reference['label'] ?? $reference['title'] ?? null);
        $sourceLabel = self::stringOrNull($reference['source_label'] ?? $reference['source'] ?? null);
        $referenceType = self::stringOrNull($reference['reference_type'] ?? $reference['source_type'] ?? null);
        $referenceId = self::stringOrNull($reference['reference_id'] ?? $reference['source_id'] ?? null);

        if ($url === null && $referenceType === null && $referenceId === null) {
            return null;
        }

        return [
            'reference_kind' => $kind,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'display_label' => $displayLabel,
            'source_label' => $sourceLabel,
            'url' => $url,
            'order_column' => $orderColumn,
            'metadata' => self::arrayOrNull($reference['metadata'] ?? null),
        ];
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value !== '' ? $value : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function arrayOrNull(mixed $value): ?array
    {
        return is_array($value) ? $value : null;
    }
}
