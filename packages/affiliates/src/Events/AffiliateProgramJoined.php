<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Events;

use AIArmada\Affiliates\Features\Programs\Api\ProgramJoinReference;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class AffiliateProgramJoined
{
    use Dispatchable;

    public function __construct(public ProgramJoinReference $reference) {}
}
