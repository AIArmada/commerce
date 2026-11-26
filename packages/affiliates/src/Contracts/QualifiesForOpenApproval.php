<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

interface QualifiesForOpenApproval
{
    public function qualifies(string $conversionId): bool;
}
