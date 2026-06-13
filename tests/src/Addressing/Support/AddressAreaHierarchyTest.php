<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\ImportAddressAreasAction;
use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\AddressAreaHierarchy;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;

beforeEach(function (): void {
    app(SeedAddressCountriesAction::class)->execute();
    $this->action = app(ImportAddressAreasAction::class);
});

it('excludes the current area and descendants from parent options', function (): void {
    $source = new ArrayAddressAreaSource('test', [
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
        new AddressAreaData(
            source: 'test',
            sourceId: 'sibling',
            countryCode: 'MY',
            type: 'state',
            name: 'Perak',
        ),
    ]);

    $this->action->execute($source);

    $country = AddressCountry::where('iso2', 'MY')->firstOrFail();
    $root = AddressArea::where('source', 'test')->where('source_id', 'root')->firstOrFail();
    $child = AddressArea::where('source', 'test')->where('source_id', 'child')->firstOrFail();
    $sibling = AddressArea::where('source', 'test')->where('source_id', 'sibling')->firstOrFail();

    $options = AddressAreaHierarchy::parentOptions($country->id, $root->id);

    expect($options)->toHaveCount(1);
    expect($options)->toHaveKey($sibling->id);
    expect($options)->not->toHaveKey($root->id);
    expect($options)->not->toHaveKey($child->id);
});
