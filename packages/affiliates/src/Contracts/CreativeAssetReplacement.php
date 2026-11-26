<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

use AIArmada\Affiliates\Data\PreparedCreativeAsset;

interface CreativeAssetReplacement
{
    public function replace(string $creativeId, string $morphType, int $connectionIdentity, PreparedCreativeAsset $asset): void;
}
