<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\ImportAddressAreasAction;
use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource\Pages\EditAddressArea;
use Illuminate\Validation\ValidationException;

it('area resource navigation can be disabled globally', function (): void {
    $original = config('filament-addressing.navigation.enabled', true);

    config()->set('filament-addressing.navigation.enabled', false);

    try {
        expect(AddressAreaResource::shouldRegisterNavigation())->toBeFalse();
    } finally {
        config()->set('filament-addressing.navigation.enabled', $original);
    }
});

it('area resource omits edit pages when read only', function (): void {
    $original = config('filament-addressing.resources.areas.read_only', false);

    config()->set('filament-addressing.resources.areas.read_only', true);

    try {
        expect(AddressAreaResource::getPages())->not->toHaveKey('edit');
    } finally {
        config()->set('filament-addressing.resources.areas.read_only', $original);
    }
});

it('rejects cyclic parents when editing areas', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $action = app(ImportAddressAreasAction::class);

    $action->execute(new ArrayAddressAreaSource('test', [
        new AddressAreaData(
            source: 'test',
            sourceId: 'root',
            countryCode: 'MY',
            type: 'state',
            name: 'Selangor',
        ),
        new AddressAreaData(
            source: 'test',
            sourceId: 'child',
            countryCode: 'MY',
            type: 'district',
            name: 'Petaling',
            parentSourceId: 'root',
        ),
    ]));

    $root = AddressArea::where('source', 'test')
        ->where('source_id', 'root')
        ->firstOrFail();

    $child = AddressArea::where('source', 'test')
        ->where('source_id', 'child')
        ->firstOrFail();

    $page = app(EditAddressArea::class);
    $page->record = $root;

    $method = new ReflectionMethod(EditAddressArea::class, 'mutateFormDataBeforeSave');
    $method->setAccessible(true);

    expect(function () use ($method, $page, $child): mixed {
        return $method->invoke($page, [
            'parent_id' => $child->id,
        ]);
    })->toThrow(ValidationException::class);
});
