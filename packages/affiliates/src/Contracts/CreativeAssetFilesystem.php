<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

interface CreativeAssetFilesystem
{
    /** @param array<string, mixed> $attributes */
    public function removeDetached(string $mediaClass, array $attributes, int $connectionIdentity): void;
}
