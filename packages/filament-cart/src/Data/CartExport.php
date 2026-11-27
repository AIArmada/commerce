<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Data;

final readonly class CartExport
{
    public function __construct(
        public string $filename,
        public string $json,
    ) {}
}
