<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Contracts;

interface PublicHtmlFetcher
{
    public function fetch(string $url): ?string;
}
