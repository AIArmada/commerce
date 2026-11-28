<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Programs\Api;

interface RewardProgramDirectoryApi
{
    public function title(string $programId): ?string;

    /** @return array<string, string> At most 25 owner-scoped matches, ordered by title and ID. */
    public function options(string $search = ''): array;

    public function versionCount(string $programId): int;

    public function currentVersion(string $programId): ?RewardVersionSummary;
}
