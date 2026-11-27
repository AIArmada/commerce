<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Contracts;

interface PageMarkupFetcher
{
    public const int MAX_HTML_BYTES = 2_097_152;

    public function fetch(string $pageUrl): ?string;
}
