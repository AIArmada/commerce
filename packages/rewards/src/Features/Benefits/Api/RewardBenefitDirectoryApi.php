<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Benefits\Api;

interface RewardBenefitDirectoryApi
{
    public function title(string $itemId): ?string;
}
