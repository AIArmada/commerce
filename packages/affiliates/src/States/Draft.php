<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\States;

final class Draft extends AffiliateStatus
{
    public static string $name = 'draft';

    public static function label(): string
    {
        return 'Draft';
    }

    public static function description(): string
    {
        return 'Affiliate has not been submitted for approval';
    }

    public static function color(): string
    {
        return 'gray';
    }

    public function isDraft(): bool
    {
        return true;
    }
}
