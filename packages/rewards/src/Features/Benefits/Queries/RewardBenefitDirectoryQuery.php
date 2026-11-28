<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Benefits\Queries;

use AIArmada\Rewards\Features\Benefits\Api\RewardBenefitDirectoryApi;
use AIArmada\Rewards\Features\Benefits\Models\RewardBenefitItem;
use AIArmada\Rewards\Features\Programs\Api\RewardProgramDirectoryApi;

final readonly class RewardBenefitDirectoryQuery implements RewardBenefitDirectoryApi
{
    public function __construct(private RewardProgramDirectoryApi $programs) {}

    public function title(string $itemId): ?string
    {
        $item = RewardBenefitItem::query()->whereKey($itemId)->first(['program_id', 'title']);

        if ($item === null || $this->programs->title($item->program_id) === null) {
            return null;
        }

        return $item->title;
    }
}
