<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

final readonly class PreparedCreativeAsset
{
    public function __construct(
        public string $bytes,
        public string $extension,
    ) {}
}
