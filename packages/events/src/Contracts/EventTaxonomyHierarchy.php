<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\EventTaxonomy;
use AIArmada\Events\Models\EventTerm;
use Illuminate\Support\Collection;

interface EventTaxonomyHierarchy
{
    public function taxonomy(string $code): ?EventTaxonomy;

    /** @return Collection<int, EventTerm> */
    public function terms(string $taxonomyCode, bool $activeOnly = true): Collection;

    /** @return list<array<string, mixed>> */
    public function tree(string $taxonomyCode, bool $activeOnly = true): array;

    /** @return array<string, string> */
    public function options(string $taxonomyCode, string $separator = ' › ', bool $activeOnly = true): array;

    /** @param list<mixed> $termIds @return list<string> */
    public function validTermIds(string $taxonomyCode, array $termIds, bool $activeOnly = true): array;

    /** @param list<mixed> $termIds @return list<string> */
    public function descendantIds(string $taxonomyCode, array $termIds, bool $activeOnly = true): array;

    /** @param list<mixed> $termIds @return list<string> */
    public function minimalTermIds(string $taxonomyCode, array $termIds, bool $activeOnly = true): array;
}

