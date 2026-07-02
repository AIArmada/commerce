<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class PassState extends State
{
    abstract public function getColor(): string;

    abstract public function getLabel(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->allowTransition(Pending::class, Issued::class)
            ->allowTransition(Issued::class, Activated::class)
            ->allowTransition(Issued::class, Cancelled::class)
            ->allowTransition(Issued::class, Revoked::class)
            ->allowTransition(Activated::class, Used::class)
            ->allowTransition(Activated::class, Cancelled::class)
            ->allowTransition(Activated::class, Revoked::class)
            ->allowTransition(Cancelled::class, Revoked::class)
            ->allowTransition(Revoked::class, Voided::class)
            ->allowTransition(Used::class, Revoked::class)
            ->allowTransition(Pending::class, Expired::class)
            ->allowTransition(Issued::class, Expired::class)
            ->allowTransition(Activated::class, Expired::class);
    }
}
