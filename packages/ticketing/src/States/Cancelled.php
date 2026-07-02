<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\States;

class Cancelled extends PassState
{
    protected static string $name = 'cancelled';

    public function getColor(): string
    {
        return 'danger';
    }

    public function getLabel(): string
    {
        return 'Cancelled';
    }
}
