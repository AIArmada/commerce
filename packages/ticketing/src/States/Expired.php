<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\States;

class Expired extends PassState
{
    protected static string $name = 'expired';

    public function getColor(): string
    {
        return 'gray';
    }

    public function getLabel(): string
    {
        return 'Expired';
    }
}
