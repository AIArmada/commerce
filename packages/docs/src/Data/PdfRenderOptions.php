<?php

declare(strict_types=1);

namespace AIArmada\Docs\Data;

final readonly class PdfRenderOptions
{
    public function __construct(
        public string $format,
        public string $orientation,
        public int $marginTop,
        public int $marginRight,
        public int $marginBottom,
        public int $marginLeft,
        public bool $fullBleed,
        public bool $printBackground,
    ) {}
}
