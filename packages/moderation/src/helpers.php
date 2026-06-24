<?php

declare(strict_types=1);

if (! function_exists('moderation_table')) {
    function moderation_table(string $key): string
    {
        return (string) config("moderation.database.tables.{$key}", $key);
    }
}

if (! function_exists('moderation_json_type')) {
    function moderation_json_type(): string
    {
        return (string) config('moderation.database.json_column_type', 'jsonb');
    }
}
