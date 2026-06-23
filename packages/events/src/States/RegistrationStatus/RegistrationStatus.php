<?php

declare(strict_types=1);

namespace AIArmada\Events\States\RegistrationStatus;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class RegistrationStatus extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->registerStatesFromDirectory(__DIR__)
            ->default(Pending::class)
            ->allowTransition(Pending::class, Confirmed::class)
            ->allowTransition(Pending::class, Cancelled::class)
            ->allowTransition(Pending::class, Waitlisted::class)
            ->allowTransition(Pending::class, Rejected::class)
            ->allowTransition(Pending::class, Interested::class)
            ->allowTransition(Pending::class, Expired::class)
            ->allowTransition(Pending::class, Completed::class)
            ->allowTransition(Confirmed::class, CheckedIn::class)
            ->allowTransition(Confirmed::class, Cancelled::class)
            ->allowTransition(Confirmed::class, NoShow::class)
            ->allowTransition(Confirmed::class, Refunded::class)
            ->allowTransition(Confirmed::class, Expired::class)
            ->allowTransition(Confirmed::class, Completed::class)
            ->allowTransition(Waitlisted::class, Pending::class)
            ->allowTransition(Waitlisted::class, Confirmed::class)
            ->allowTransition(Waitlisted::class, Cancelled::class)
            ->allowTransition(Waitlisted::class, Rejected::class)
            ->allowTransition(Waitlisted::class, Expired::class)
            ->allowTransition(Interested::class, Confirmed::class)
            ->allowTransition(Interested::class, Cancelled::class)
            ->allowTransition(Interested::class, Expired::class)
            ->allowTransition(Interested::class, Completed::class)
            ->allowTransition(CheckedIn::class, Completed::class)
            ->allowTransition(CheckedIn::class, NoShow::class)
            ->allowTransition(Refunded::class, Cancelled::class);
    }

    public static function options(): array
    {
        return [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'cancelled' => 'Cancelled',
            'rejected' => 'Rejected',
            'waitlisted' => 'Waitlisted',
            'checked_in' => 'Checked In',
            'no_show' => 'No Show',
            'interested' => 'Interested',
            'refunded' => 'Refunded',
            'completed' => 'Completed',
            'expired' => 'Expired',
        ];
    }

    /** @return string[] */
    public static function capacityBlockingStates(): array
    {
        return [
            Pending::class,
            Confirmed::class,
            CheckedIn::class,
            NoShow::class,
        ];
    }

    /** @return string[] */
    public static function checkInAllowedStates(): array
    {
        return [
            Confirmed::class,
        ];
    }

    /** @return string[] */
    public static function terminalStates(): array
    {
        return [
            CheckedIn::class,
            Cancelled::class,
            Refunded::class,
            NoShow::class,
        ];
    }

    abstract public function label(): string;
}
