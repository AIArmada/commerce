<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Contracts;

interface AffiliateRelationContribution
{
    /** @return array<int, class-string> */
    public function relations(): array;
}
