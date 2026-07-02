<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\States;

class Issued extends PassState
{
    protected static string $name = 'issued';

    public function getColor(): string
    {
        return 'primary';
    }

    public function getLabel(): string
    {
        return 'Issued';
    }
}
