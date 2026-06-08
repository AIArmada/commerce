<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Support;

use AIArmada\Events\Contracts\EventSearchEngine;
use AIArmada\Events\Data\EventSearchCriteria;
use AIArmada\Events\Data\EventSearchResultData;

/**
 * Translates Filament table filter state into the package-native
 * EventSearchCriteria DTO consumed by EventQueryService / EventSearchEngine.
 *
 * This is the bridge that lets filament-events stay an adapter: Filament
 * surfaces still own their own table queries (Filament needs Eloquent models
 * for action URLs and bulk actions), but they can opt into the same
 * package-native filter semantics by delegating counts and snapshot reads
 * to EventSearchEngine.
 */
final class FilamentEventQueryAdapter
{
    /**
     * @param  array<string, mixed>  $filterState  Filament table filter state, keyed by filter name.
     * @param  array{page?: int, perPage?: int, sort?: string|null, direction?: string, term?: string|null, includeGlobal?: bool}  $context
     */
    public static function buildCriteria(array $filterState, array $context = []): EventSearchCriteria
    {
        $statuses = self::stringList($filterState['status'] ?? null);
        $moderationStatuses = self::stringList($filterState['moderation_status'] ?? null);
        $visibilities = self::stringList($filterState['visibility'] ?? null);
        $structures = self::stringList($filterState['structure'] ?? null);
        $classificationGroups = self::stringList($filterState['classification_group'] ?? null);
        $assetRoles = self::stringList($filterState['asset_role'] ?? null);
        $referenceKinds = self::stringList($filterState['reference_kind'] ?? null);

        $term = self::stringOrNull($context['term'] ?? $filterState['term'] ?? null);

        $sort = self::stringOrNull($context['sort'] ?? null);
        $direction = mb_strtolower((string) ($context['direction'] ?? 'desc'));
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        $page = max(1, (int) ($context['page'] ?? 1));
        $perPage = max(1, (int) ($context['perPage'] ?? 20));

        return new EventSearchCriteria(
            term: $term,
            statuses: $statuses,
            moderationStatuses: $moderationStatuses,
            visibilities: $visibilities,
            structures: $structures,
            referenceKinds: $referenceKinds,
            classificationGroups: $classificationGroups,
            assetRoles: $assetRoles,
            publishedAfter: null,
            publishedBefore: null,
            page: $page,
            perPage: $perPage,
            sort: $sort,
            direction: $direction,
            includeGlobal: (bool) ($context['includeGlobal'] ?? false),
        );
    }

    /**
     * Run the package's search engine against the given Filament filter state.
     * Useful for badges, counts, and snapshot reads that should match the
     * canonical package search semantics.
     *
     * @param  array<string, mixed>  $filterState
     */
    public static function search(
        array $filterState,
        array $context = [],
        ?EventSearchEngine $searchEngine = null,
    ): EventSearchResultData {
        $searchEngine ??= app(EventSearchEngine::class);

        return $searchEngine->search(self::buildCriteria($filterState, $context));
    }

    /**
     * @return array<int, string>
     */
    private static function stringList(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            $value = [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): ?string => is_string($item) && mb_trim($item) !== ''
                ? mb_trim($item)
                : null,
            $value,
        )));
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = mb_trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
