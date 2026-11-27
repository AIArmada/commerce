<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Contracts;

interface PageMarkupFetcher
{
    public function fetch(string $pageUrl): ?string;
}
