<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Support;

trait ResolvesAddressingResources
{
    public static function isResourceEnabled(string $resource): bool
    {
        return (bool) config("filament-addressing.resources.{$resource}.enabled", false);
    }

    public static function isResourceReadOnly(string $resource): bool
    {
        return (bool) config("filament-addressing.resources.{$resource}.read_only", true);
    }
}
