<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\States;

class Used extends PassState
{
    protected static string $name = 'used';

    public function getColor(): string
    {
        return 'warning';
    }

    public function getLabel(): string
    {
        return 'Used';
    }
}
