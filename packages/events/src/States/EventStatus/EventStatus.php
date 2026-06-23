<?php

declare(strict_types=1);

namespace AIArmada\Events\States\EventStatus;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class EventStatus extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->registerState(Published::class)
            ->registerState(Draft::class)
            ->registerState(PendingReview::class)
            ->registerState(Scheduled::class)
            ->registerState(Completed::class)
            ->registerState(Cancelled::class)
            ->registerState(Delayed::class)
            ->registerState(Postponed::class)
            ->registerState(Rescheduled::class)
            ->registerState(Archived::class)
            ->registerState(Expired::class)
            ->registerState(Voided::class)
            ->default(Draft::class)
            ->allowTransition(Draft::class, PendingReview::class)
            ->allowTransition(Draft::class, Scheduled::class)
            ->allowTransition(Draft::class, Archived::class)
            ->allowTransition(PendingReview::class, Scheduled::class)
            ->allowTransition(PendingReview::class, Published::class)
            ->allowTransition(PendingReview::class, Cancelled::class)
            ->allowTransition(PendingReview::class, Archived::class)
            ->allowTransition(Scheduled::class, Published::class)
            ->allowTransition(Scheduled::class, Delayed::class)
            ->allowTransition(Scheduled::class, Postponed::class)
            ->allowTransition(Scheduled::class, Cancelled::class)
            ->allowTransition(Scheduled::class, Completed::class)
            ->allowTransition(Scheduled::class, Archived::class)
            ->allowTransition(Published::class, Delayed::class)
            ->allowTransition(Published::class, Postponed::class)
            ->allowTransition(Published::class, Rescheduled::class)
            ->allowTransition(Published::class, Cancelled::class)
            ->allowTransition(Published::class, Completed::class)
            ->allowTransition(Published::class, Archived::class)
            ->allowTransition(Delayed::class, Published::class)
            ->allowTransition(Delayed::class, Postponed::class)
            ->allowTransition(Delayed::class, Rescheduled::class)
            ->allowTransition(Delayed::class, Cancelled::class)
            ->allowTransition(Delayed::class, Completed::class)
            ->allowTransition(Postponed::class, Rescheduled::class)
            ->allowTransition(Postponed::class, Published::class)
            ->allowTransition(Postponed::class, Cancelled::class)
            ->allowTransition(Rescheduled::class, Published::class)
            ->allowTransition(Rescheduled::class, Delayed::class)
            ->allowTransition(Rescheduled::class, Postponed::class)
            ->allowTransition(Rescheduled::class, Cancelled::class)
            ->allowTransition(Rescheduled::class, Completed::class)
            ->allowTransition(Cancelled::class, Rescheduled::class)
            ->allowTransition(Cancelled::class, Archived::class)
            ->allowTransition(Completed::class, Archived::class)
            ->allowTransition(Expired::class, Archived::class);
    }

    public static function options(): array
    {
        return [
            'draft' => 'Draft',
            'pending_review' => 'Pending Review',
            'scheduled' => 'Scheduled',
            'published' => 'Published',
            'delayed' => 'Delayed',
            'postponed' => 'Postponed',
            'rescheduled' => 'Rescheduled',
            'cancelled' => 'Cancelled',
            'completed' => 'Completed',
            'archived' => 'Archived',
            'voided' => 'Voided',
            'expired' => 'Expired',
        ];
    }

    abstract public function label(): string;
}
