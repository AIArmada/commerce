<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

interface TrackingProgramGuard
{
    public function assertReference(string $programId, int $connectionIdentity): void;

    public function lockForTracking(string $programId, int $connectionIdentity): void;
}
