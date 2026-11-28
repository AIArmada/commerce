<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Programs\Api;

interface RewardProgramDirectoryApi
{
    public function title(string $programId): ?string;

    /** @return array<string, string> */
    public function options(): array;

    public function versionCount(string $programId): int;

    public function currentVersion(string $programId): ?RewardVersionSummary;
}
