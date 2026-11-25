<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

interface ProgramJoinReader
{
    public function find(ProgramJoinReference $reference): ?ProgramJoinRecord;
}
