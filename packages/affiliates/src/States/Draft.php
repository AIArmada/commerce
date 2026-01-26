<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\States;

final class Draft extends AffiliateStatus
{
    public static string $name = 'draft';

    public function label(): string
    {
        return 'Draft';
    }

    public function description(): string
    {
        return 'Affiliate has not been submitted for approval';
    }

    public function isDraft(): bool
    {
        return true;
    }
}
