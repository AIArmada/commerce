<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

interface CreativeAssetReplacement
{
    public function replace(string $creativeId, string $morphType, int $connectionIdentity, string $bytes): void;
}
