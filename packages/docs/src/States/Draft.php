<?php

declare(strict_types=1);

namespace AIArmada\Docs\States;

final class Draft extends DocStatus
{
    public static string $name = 'draft';

    public function label(): string
    {
        return 'Draft';
    }

    public function color(): string
    {
        return 'gray';
    }
}