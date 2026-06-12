<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface RequiresEventApproval
{
    public function eventApprovalRequiredFor(string $action): bool;

    public function eventApproversFor(string $action): iterable;
}
