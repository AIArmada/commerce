<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Support;

class DefaultAbilityToPermissionMapper
{
    public function __invoke(string $modelClass, string $ability): string
    {
        $base = class_basename($modelClass);

        return mb_strtolower($base).'.'.$ability; // e.g. user.viewAny
    }
}
