<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Support;

class GuardsAddressingUi
{
    public static function assertAddressResourceEnabled(): void
    {
        if (! config('filament-addressing.resources.addresses.enabled')) {
            abort(404, 'Address resource is not enabled.');
        }
    }

    public static function assertEditable(string $resource): void
    {
        if ((bool) config("filament-addressing.resources.{$resource}.read_only", true)) {
            abort(403, "{$resource} resource is read-only.");
        }
    }

    public static function canEdit(string $resource): bool
    {
        return ! (bool) config("filament-addressing.resources.{$resource}.read_only", true);
    }
}
