<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\States;

class Pending extends PassState
{
    protected static string $name = 'pending';

    public function getColor(): string
    {
        return 'gray';
    }

    public function getLabel(): string
    {
        return 'Pending';
    }
}
