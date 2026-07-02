<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\States;

class Revoked extends PassState
{
    protected static string $name = 'revoked';

    public function getColor(): string
    {
        return 'danger';
    }

    public function getLabel(): string
    {
        return 'Revoked';
    }
}
