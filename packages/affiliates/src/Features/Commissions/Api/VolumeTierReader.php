<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Api;

interface VolumeTierReader
{
    /** @return list<VolumeTierSnapshot> */
    public function forProgram(string $programId): array;
}
