<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums\Concerns;

trait HasLabelOptions
{
    public static function options(): array
    {
        $options = [];
        foreach (static::cases() as $case) {
            $options[(string) $case->value] = $case->label();
        }

        return $options;
    }
}
