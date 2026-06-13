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

it('area resource is enabled by default', function (): void {
    expect(config('filament-addressing.resources.areas.enabled'))->toBeTrue();
});

it('area resource is not read-only by default', function (): void {
    expect(AddressAreaResource::isReadOnly())->toBeFalse();
});

it('area resource navigation sort is configured', function (): void {
    expect(AddressAreaResource::getNavigationSort())->toBe(81);
});

it('area resource navigation icon follows config', function (): void {
    $original = config('filament-addressing.navigation.icons.areas');

    config()->set('filament-addressing.navigation.icons.areas', 'heroicon-o-folder-open');

    try {
        expect(AddressAreaResource::getNavigationIcon())->toBe('heroicon-o-folder-open');
    } finally {
        config()->set('filament-addressing.navigation.icons.areas', $original);
    }
});

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

it('area resource has correct model', function (): void {
    expect(AddressAreaResource::getModel())->toBe(AddressArea::class);
});

it('area import is enabled by default', function (): void {
    expect(config('filament-addressing.features.area_import'))->toBeTrue();
});

it('area export is enabled by default', function (): void {
    expect(config('filament-addressing.features.area_export'))->toBeTrue();
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
