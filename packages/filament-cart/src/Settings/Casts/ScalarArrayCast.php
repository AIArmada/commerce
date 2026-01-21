<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Settings\Casts;

use Spatie\LaravelSettings\SettingsCasts\SettingsCast;

final class ScalarArrayCast implements SettingsCast
{
    public function get(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        return $payload;
    }

    public function set(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        return $payload;
    }
}
