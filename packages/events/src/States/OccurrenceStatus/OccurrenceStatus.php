<?php

declare(strict_types=1);

namespace AIArmada\Events\States\OccurrenceStatus;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class OccurrenceStatus extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->registerStatesFromDirectory(__DIR__)
            ->default(Draft::class)
            ->allowTransition(Draft::class, Scheduled::class)
            ->allowTransition(Draft::class, Published::class)
            ->allowTransition(Draft::class, Cancelled::class)
            ->allowTransition(Draft::class, Archived::class)
            ->allowTransition(Scheduled::class, Published::class)
            ->allowTransition(Scheduled::class, Archived::class)
            ->allowTransition(Scheduled::class, Live::class)
            ->allowTransition(Scheduled::class, Delayed::class)
            ->allowTransition(Scheduled::class, Postponed::class)
            ->allowTransition(Scheduled::class, Rescheduled::class)
            ->allowTransition(Scheduled::class, Cancelled::class)
            ->allowTransition(Scheduled::class, Completed::class)
            ->allowTransition(Published::class, Live::class)
            ->allowTransition(Published::class, Archived::class)
            ->allowTransition(Published::class, Delayed::class)
            ->allowTransition(Published::class, Postponed::class)
            ->allowTransition(Published::class, Rescheduled::class)
            ->allowTransition(Published::class, Cancelled::class)
            ->allowTransition(Published::class, Completed::class)
            ->allowTransition(Live::class, Completed::class)
            ->allowTransition(Live::class, Archived::class)
            ->allowTransition(Live::class, Delayed::class)
            ->allowTransition(Live::class, Postponed::class)
            ->allowTransition(Live::class, Cancelled::class)
            ->allowTransition(Delayed::class, Scheduled::class)
            ->allowTransition(Delayed::class, Published::class)
            ->allowTransition(Delayed::class, Live::class)
            ->allowTransition(Delayed::class, Archived::class)
            ->allowTransition(Delayed::class, Rescheduled::class)
            ->allowTransition(Delayed::class, Cancelled::class)
            ->allowTransition(Postponed::class, Rescheduled::class)
            ->allowTransition(Postponed::class, Scheduled::class)
            ->allowTransition(Postponed::class, Archived::class)
            ->allowTransition(Postponed::class, Cancelled::class)
            ->allowTransition(Rescheduled::class, Scheduled::class)
            ->allowTransition(Rescheduled::class, Published::class)
            ->allowTransition(Rescheduled::class, Live::class)
            ->allowTransition(Rescheduled::class, Archived::class)
            ->allowTransition(Rescheduled::class, Cancelled::class)
            ->allowTransition(Cancelled::class, Archived::class)
            ->allowTransition(Completed::class, Archived::class);
    }

    public static function options(): array
    {
        return [
            'draft' => 'Draft',
            'scheduled' => 'Scheduled',
            'published' => 'Published',
            'live' => 'Live',
            'delayed' => 'Delayed',
            'postponed' => 'Postponed',
            'rescheduled' => 'Rescheduled',
            'cancelled' => 'Cancelled',
            'completed' => 'Completed',
            'archived' => 'Archived',
        ];
    }

    abstract public function label(): string;
}
