<?php

declare(strict_types=1);

namespace AIArmada\Events\Support\Policy;

use AIArmada\Events\Enums\OccurrenceStatus;
use AIArmada\Events\Enums\RegistrationStatus;
use BackedEnum;

final class LifecyclePolicy
{
    public static function occurrenceAcceptsRegistrations(OccurrenceStatus | string $status): bool
    {
        return in_array(self::statusValue($status), self::occurrenceRegistrationAcceptingValues(), true);
    }

    public static function occurrenceAcceptsCheckIn(OccurrenceStatus | string $status): bool
    {
        return in_array(self::statusValue($status), self::occurrenceCheckInAcceptingValues(), true);
    }

    public static function occurrenceAcceptsWalkIns(OccurrenceStatus | string $status): bool
    {
        return in_array(self::statusValue($status), self::occurrenceWalkInAcceptingValues(), true);
    }

    public static function registrationCanCheckIn(RegistrationStatus | string $status): bool
    {
        return in_array(self::statusValue($status), self::registrationCheckInAllowedValues(), true);
    }

    /**
     * @return list<string>
     */
    public static function registrationCapacityBlockingValues(): array
    {
        return self::configuredStatusValues(
            config('events.lifecycle.registration.capacity_blocking_statuses'),
            [
                RegistrationStatus::Pending->value,
                RegistrationStatus::Confirmed->value,
                RegistrationStatus::CheckedIn->value,
                RegistrationStatus::NoShow->value,
            ],
        );
    }

    public static function registrationBlocksCapacity(RegistrationStatus | string $status): bool
    {
        return in_array(self::statusValue($status), self::registrationCapacityBlockingValues(), true);
    }

    public static function registrationIsTerminal(RegistrationStatus | string $status): bool
    {
        return in_array(self::statusValue($status), self::registrationTerminalValues(), true);
    }

    /**
     * @return list<string>
     */
    private static function occurrenceRegistrationAcceptingValues(): array
    {
        return self::configuredStatusValues(
            config('events.lifecycle.occurrence.registration_accepting_statuses'),
            [
                OccurrenceStatus::Scheduled->value,
                OccurrenceStatus::Live->value,
            ],
        );
    }

    /**
     * @return list<string>
     */
    private static function occurrenceCheckInAcceptingValues(): array
    {
        return self::configuredStatusValues(
            config('events.lifecycle.occurrence.check_in_accepting_statuses'),
            [
                OccurrenceStatus::Scheduled->value,
                OccurrenceStatus::Live->value,
            ],
        );
    }

    /**
     * @return list<string>
     */
    private static function occurrenceWalkInAcceptingValues(): array
    {
        return self::configuredStatusValues(
            config('events.lifecycle.occurrence.walk_in_accepting_statuses'),
            [
                OccurrenceStatus::Scheduled->value,
                OccurrenceStatus::Live->value,
            ],
        );
    }

    /**
     * @return list<string>
     */
    private static function registrationCheckInAllowedValues(): array
    {
        return self::configuredStatusValues(
            config('events.lifecycle.registration.check_in_allowed_statuses'),
            [RegistrationStatus::Confirmed->value],
        );
    }

    /**
     * @return list<string>
     */
    private static function registrationTerminalValues(): array
    {
        return self::configuredStatusValues(
            config('events.lifecycle.registration.terminal_statuses'),
            [
                RegistrationStatus::CheckedIn->value,
                RegistrationStatus::Cancelled->value,
                RegistrationStatus::Refunded->value,
                RegistrationStatus::NoShow->value,
            ],
        );
    }

    private static function statusValue(BackedEnum | string $status): string
    {
        if ($status instanceof BackedEnum) {
            return (string) $status->value;
        }

        return $status;
    }

    /**
     * @param  list<string>  $default
     * @return list<string>
     */
    private static function configuredStatusValues(mixed $configured, array $default): array
    {
        if (! is_array($configured)) {
            return $default;
        }

        $values = [];

        foreach ($configured as $status) {
            if ($status instanceof BackedEnum) {
                $values[] = (string) $status->value;

                continue;
            }

            if (! is_scalar($status)) {
                continue;
            }

            $value = mb_trim((string) $status);

            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values === [] ? $default : array_values(array_unique($values));
    }
}
