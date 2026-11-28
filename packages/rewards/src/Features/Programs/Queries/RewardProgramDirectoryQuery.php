<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Programs\Queries;

use AIArmada\Rewards\Features\Programs\Api\RewardProgramDirectoryApi;
use AIArmada\Rewards\Features\Programs\Api\RewardVersionSummary;
use AIArmada\Rewards\Features\Programs\Models\RewardProgram;
use AIArmada\Rewards\Features\Programs\Models\RewardProgramVersion;

final readonly class RewardProgramDirectoryQuery implements RewardProgramDirectoryApi
{
    public function title(string $programId): ?string
    {
        $title = RewardProgram::query()->whereKey($programId)->value('title');

        return is_string($title) ? $title : null;
    }

    public function options(string $search = ''): array
    {
        $search = mb_trim($search);
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search));

        $query = RewardProgram::query();

        if ($escaped !== '') {
            $query->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", ["%{$escaped}%"]);
        }

        return $query->orderBy('title')->orderBy('id')->limit(25)->pluck('title', 'id')->all();
    }

    public function versionCount(string $programId): int
    {
        return RewardProgramVersion::query()
            ->where('program_id', $programId)
            ->whereIn('program_id', RewardProgram::query()->whereKey($programId)->select('id'))
            ->count();
    }

    public function currentVersion(string $programId): ?RewardVersionSummary
    {
        $version = RewardProgramVersion::query()
            ->where('program_id', $programId)
            ->whereIn('id', RewardProgram::query()->whereKey($programId)->select('current_version_id'))
            ->first(['version', 'effective_from', 'material_terms_hash']);

        return $version === null ? null : new RewardVersionSummary(
            $version->version,
            $version->effective_from->setTimezone('UTC')->toIso8601String(),
            $version->material_terms_hash,
        );
    }
}
