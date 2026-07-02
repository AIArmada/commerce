<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\States;

class Voided extends PassState
{
    protected static string $name = 'voided';

    public function getColor(): string
    {
        return 'gray';
    }

    public function getLabel(): string
    {
        return 'Voided';
    }
}
