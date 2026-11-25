<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

interface PayoutCsvWriter
{
    /** @param list<array{string, string, int, string, string}> $rows */
    public function write(string $path, array $rows): void;
}
