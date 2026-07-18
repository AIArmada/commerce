<?php

declare(strict_types=1);

namespace AIArmada\Events\Support;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventAttendance;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventSubmission;

/**
 * Resolves host application model subclasses configured for the events package.
 */
final class ModelResolver
{
    /**
     * @return class-string<Event>
     */
    public static function eventClass(): string
    {
        /** @var class-string<Event> $modelClass */
        $modelClass = config('events.models.event', Event::class);

        return $modelClass;
    }

    /**
     * @return class-string<EventRegistration>
     */
    public static function registrationClass(): string
    {
        /** @var class-string<EventRegistration> $modelClass */
        $modelClass = config('events.models.registration', EventRegistration::class);

        return $modelClass;
    }

    /**
     * @return class-string<EventAttendance>
     */
    public static function attendanceClass(): string
    {
        /** @var class-string<EventAttendance> $modelClass */
        $modelClass = config('events.models.attendance', EventAttendance::class);

        return $modelClass;
    }

    /**
     * @return class-string<EventSubmission>
     */
    public static function submissionClass(): string
    {
        /** @var class-string<EventSubmission> $modelClass */
        $modelClass = config('events.models.submission', EventSubmission::class);

        return $modelClass;
    }
}
