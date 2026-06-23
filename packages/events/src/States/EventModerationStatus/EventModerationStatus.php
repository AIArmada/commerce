<?php

declare(strict_types=1);

namespace AIArmada\Events\States\EventModerationStatus;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class EventModerationStatus extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->registerStatesFromDirectory(__DIR__)
            ->default(Draft::class)
            ->allowTransition(Draft::class, Pending::class)
            ->allowTransition(Pending::class, Approved::class)
            ->allowTransition(Pending::class, ChangesRequested::class)
            ->allowTransition(Pending::class, Rejected::class)
            ->allowTransition(Approved::class, ChangesRequested::class)
            ->allowTransition(Approved::class, Rejected::class)
            ->allowTransition(Approved::class, Pending::class)
            ->allowTransition(ChangesRequested::class, Pending::class)
            ->allowTransition(ChangesRequested::class, Rejected::class)
            ->allowTransition(Rejected::class, Pending::class)
            ->allowTransition(
                [Draft::class, Pending::class, Approved::class, ChangesRequested::class, Rejected::class],
                Converted::class,
            );
    }

    public static function options(): array
    {
        return [
            'draft' => 'Draft',
            'pending' => 'Pending',
            'approved' => 'Approved',
            'changes_requested' => 'Changes Requested',
            'rejected' => 'Rejected',
            'converted' => 'Converted',
        ];
    }

    abstract public function label(): string;
}
