<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\ImportAddressAreasAction;
use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SyncAddressAreaAssignmentsAction;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    app(SeedAddressCountriesAction::class)->execute();
    app(ImportAddressAreasAction::class)->execute(new ArrayAddressAreaSource('areas', [
        new AddressAreaData(source: 'areas', sourceId: 'postal', countryCode: 'MY', type: 'locality', name: 'Bangsar'),
        new AddressAreaData(source: 'areas', sourceId: 'admin', countryCode: 'MY', type: 'district', name: 'Petaling'),
    ]));
});

it('syncs typed postal and administrative assignments', function (): void {
    $address = Address::query()->create(['country_code' => 'MY', 'country' => 'Malaysia']);
    $areas = AddressArea::query()->where('source', 'areas')->get()->keyBy('source_id');

    app(SyncAddressAreaAssignmentsAction::class)->execute($address, [
        'postal_locality' => $areas['postal']->getKey(),
        'administrative_district' => $areas['admin']->getKey(),
    ]);

    expect($address->areaAssignments()->count())->toBe(2)
        ->and($address->areaAssignments()->where('role', 'postal_locality')->exists())->toBeTrue()
        ->and($address->areaAssignments()->where('role', 'administrative_district')->exists())->toBeTrue();
});

it('rejects areas from another country or role branch', function (): void {
    $address = Address::query()->create(['country_code' => 'MY', 'country' => 'Malaysia']);
    $admin = AddressArea::query()->where('source_id', 'admin')->firstOrFail();

    expect(fn (): mixed => app(SyncAddressAreaAssignmentsAction::class)->execute($address, [
        'postal_locality' => $admin->getKey(),
    ]))->toThrow(ValidationException::class);
});
