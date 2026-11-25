<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

interface TrackingProgramGuard
{
    public function lockForTracking(string $programId, int $connectionIdentity): void;
}
