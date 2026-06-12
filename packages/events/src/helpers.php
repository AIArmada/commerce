<?php

declare(strict_types=1);

use Illuminate\Support\Str;

if (! function_exists('events_table')) {
    function events_table(string $key): string
    {
        return (string) config("events.database.tables.{$key}", $key);
    }
}

if (! function_exists('events_json_type')) {
    function events_json_type(): string
    {
        return (string) config('events.database.json_column_type', 'jsonb');
    }
}

if (! function_exists('event_registration_no')) {
    function event_registration_no(?string $prefix = null): string
    {
        $prefix ??= config('events.codes.registration_prefix', 'REG');
        $length = (int) config('events.codes.registration_length', 10);

        return $prefix . '-' . strtoupper(Str::random(max(6, $length)));
    }
}
