<?php

declare(strict_types=1);

if (! function_exists('events_table')) {
    function events_table(string $key): string
    {
        return (string) config("events.database.tables.{$key}", $key);
    }
}

if (! function_exists('events_json_type')) {
    function events_json_type(): string
    {
        return (string) commerce_json_column_type('events', 'jsonb');
    }
}
