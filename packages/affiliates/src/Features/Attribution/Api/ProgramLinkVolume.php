<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Api;

final readonly class ProgramLinkVolume
{
    /** @param array<string, int> $byCurrency */
    public function __construct(public int $count, public array $byCurrency) {}
}
