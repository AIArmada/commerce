<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

final class TrackingScanTime
{
    public static function normalize(?DateTimeInterface $time): ?CarbonImmutable
    {
        if ($time === null) {
            return null;
        }

        return CarbonImmutable::instance($time)->setTimezone(self::storageTimezone());
    }

    public static function forStorage(?DateTimeInterface $time): ?string
    {
        return self::normalize($time)?->format('Y-m-d H:i:s.u');
    }

    public static function storageTimezone(): string
    {
        $connectionTimezone = DB::connection()->getConfig('timezone');

        if (is_string($connectionTimezone) && mb_trim($connectionTimezone) !== '') {
            return mb_trim($connectionTimezone);
        }

        $appTimezone = config('app.timezone', 'UTC');

        return is_string($appTimezone) && mb_trim($appTimezone) !== '' ? mb_trim($appTimezone) : 'UTC';
    }
}
