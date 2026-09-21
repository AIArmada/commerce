<?php

declare(strict_types=1);

use AIArmada\FilamentAddressing\Resources\AddressResource\Pages\ListAddresses;
use Filament\Actions\ExportAction;

it('address export action is toggled by config', function (): void {
    $original = config('filament-addressing.features.address_export', false);

    $page = new ListAddresses;
    $method = new ReflectionMethod($page, 'getHeaderActions');
    $method->setAccessible(true);

    config()->set('filament-addressing.features.address_export', false);

    try {
        $actions = $method->invoke($page);

        expect(collect($actions)->contains(fn ($action): bool => $action instanceof ExportAction))->toBeFalse();

        config()->set('filament-addressing.features.address_export', true);

        $actions = $method->invoke($page);

        expect(collect($actions)->contains(fn ($action): bool => $action instanceof ExportAction))->toBeTrue();
    } finally {
        config()->set('filament-addressing.features.address_export', $original);
    }
});
