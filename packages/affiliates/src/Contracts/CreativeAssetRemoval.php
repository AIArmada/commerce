<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

interface CreativeAssetRemoval
{
    public function remove(string $creativeId, string $morphType, int $connectionIdentity): void;
}
