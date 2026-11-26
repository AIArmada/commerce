<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline\Api;

interface RankAssignmentGuard
{
    public function assertAssignable(string $rankId): void;
}
