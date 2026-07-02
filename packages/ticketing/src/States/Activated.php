<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\States;

class Activated extends PassState
{
    protected static string $name = 'activated';

    public function getColor(): string
    {
        return 'success';
    }

    public function getLabel(): string
    {
        return 'Activated';
    }
}
