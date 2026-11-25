<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Data\EventTaxonomyData;
use AIArmada\Events\Data\EventTermData;

interface EventTaxonomyHierarchy
{
    public function taxonomy(string $code): ?EventTaxonomyData;

    /**
     * @return list<EventTermData>
     */
    public function terms(string $taxonomyCode, bool $activeOnly = true): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function tree(string $taxonomyCode, bool $activeOnly = true): array;

    /**
     * @return array<string, string>
     */
    public function options(string $taxonomyCode, string $separator = ' › ', bool $activeOnly = true): array;

    /**
     * @param  list<mixed>  $termIds
     * @return list<string>
     */
    public function validTermIds(string $taxonomyCode, array $termIds, bool $activeOnly = true): array;

    /**
     * @param  list<mixed>  $termIds
     * @return list<string>
     */
    public function descendantIds(string $taxonomyCode, array $termIds, bool $activeOnly = true): array;

    /**
     * @param  list<mixed>  $termIds
     * @return list<string>
     */
    public function minimalTermIds(string $taxonomyCode, array $termIds, bool $activeOnly = true): array;

    /**
     * @return list<array{id: string, label: string, code: string}>
     */
    public function searchTerms(string $taxonomyCode, ?string $search = null, int $limit = 50): array;
}
