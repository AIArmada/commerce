<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\ImportAddressAreasAction;
use AIArmada\Addressing\Actions\SyncAddressAreaAssignmentsAction;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaAssignment;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;

beforeEach(function (): void {
    $this->seedCountry('MY');
    $this->seedCountry('SG');
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $this->country = $country;
    $state = State::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'code' => '01',
        'name' => 'Johor',
    ]);

    app(ImportAddressAreasAction::class)->execute(new ArrayAddressAreaSource('areas', [
        new AddressAreaData(source: 'areas', sourceId: 'state', countryCode: 'MY', type: 'state', level: 1, name: 'Johor'),
        new AddressAreaData(source: 'areas', sourceId: 'postal', countryCode: 'MY', type: 'locality', level: 2, name: 'Bangsar', parentSourceId: 'state', hierarchyType: 'postal'),
        new AddressAreaData(source: 'areas', sourceId: 'admin', countryCode: 'MY', type: 'district', level: 2, name: 'Petaling', parentSourceId: 'state', hierarchyType: 'administrative'),
    ]));

    $stateArea = AddressArea::query()->where('source_id', 'state')->firstOrFail();
    AddressAreaStateLink::query()->create([
        'address_area_id' => $stateArea->getKey(),
        'state_id' => $state->getKey(),
    ]);
    $this->state = $state;
    $this->stateArea = $stateArea;
});

it('keeps assignments when unrelated address fields change', function (): void {
    $address = Address::query()->create([
        'country_code' => 'MY',
        'country' => 'Malaysia',
        'state_id' => $this->state->getKey(),
    ]);
    $areas = AddressArea::query()->where('source', 'areas')->get()->keyBy('source_id');

    app(SyncAddressAreaAssignmentsAction::class)->execute($address, [
        'postal_locality' => $areas['postal']->getKey(),
        'administrative_district' => $areas['admin']->getKey(),
    ]);

    $address->update(['line1' => '123 Jalan Ampang']);

    expect($address->areaAssignments()->count())->toBe(2);
});

it('clears assignments that no longer belong to the new state', function (): void {
    $address = Address::query()->create([
        'country_code' => 'MY',
        'country' => 'Malaysia',
        'state_id' => $this->state->getKey(),
    ]);
    $areas = AddressArea::query()->where('source', 'areas')->get()->keyBy('source_id');

    app(SyncAddressAreaAssignmentsAction::class)->execute($address, [
        'postal_locality' => $areas['postal']->getKey(),
        'administrative_district' => $areas['admin']->getKey(),
    ]);

    $otherState = State::query()->create([
        'country_id' => $this->country->getKey(),
        'country_code' => 'MY',
        'code' => '02',
        'name' => 'Kedah',
    ]);

    $address->update(['state_id' => $otherState->getKey()]);

    expect($address->fresh()?->state_id)->toBe($otherState->getKey())
        ->and($address->areaAssignments()->exists())->toBeFalse();
});

it('retains assignments that stay valid under the new state', function (): void {
    $address = Address::query()->create([
        'country_code' => 'MY',
        'country' => 'Malaysia',
        'state_id' => $this->state->getKey(),
    ]);
    $areas = AddressArea::query()->where('source', 'areas')->get()->keyBy('source_id');

    app(SyncAddressAreaAssignmentsAction::class)->execute($address, [
        'postal_locality' => $areas['postal']->getKey(),
        'administrative_district' => $areas['admin']->getKey(),
    ]);

    $siblingState = State::query()->create([
        'country_id' => $this->country->getKey(),
        'country_code' => 'MY',
        'code' => '01B',
        'name' => 'Johor Bahru',
    ]);
    AddressAreaStateLink::query()->create([
        'address_area_id' => $this->stateArea->getKey(),
        'state_id' => $siblingState->getKey(),
    ]);

    $address->update(['state_id' => $siblingState->getKey()]);

    expect($address->fresh()?->state_id)->toBe($siblingState->getKey())
        ->and($address->areaAssignments()->count())->toBe(2);
});

it('clears assignments when the country changes', function (): void {
    $address = Address::query()->create([
        'country_code' => 'MY',
        'country' => 'Malaysia',
        'state_id' => $this->state->getKey(),
    ]);
    $areas = AddressArea::query()->where('source', 'areas')->get()->keyBy('source_id');

    app(SyncAddressAreaAssignmentsAction::class)->execute($address, [
        'postal_locality' => $areas['postal']->getKey(),
        'administrative_district' => $areas['admin']->getKey(),
    ]);

    $address->update([
        'country_code' => 'SG',
        'country_id' => null,
        'state_id' => null,
    ]);

    expect($address->fresh()?->country_code)->toBe('SG')
        ->and($address->areaAssignments()->exists())->toBeFalse();
});

it('prunes a numeric zero role while retaining the valid role', function (): void {
    $address = Address::query()->create([
        'country_code' => 'MY',
        'country' => 'Malaysia',
        'state_id' => $this->state->getKey(),
    ]);
    $areas = AddressArea::query()->where('source', 'areas')->get()->keyBy('source_id');

    app(SyncAddressAreaAssignmentsAction::class)->execute($address, [
        'postal_locality' => $areas['postal']->getKey(),
    ]);

    AddressAreaAssignment::query()->create([
        'address_id' => $address->getKey(),
        'address_area_id' => $areas['postal']->getKey(),
        'role' => '0',
        'is_primary' => true,
    ]);

    app(SyncAddressAreaAssignmentsAction::class)->pruneIncompatibleAssignments($address);

    expect($address->areaAssignments()->where('role', 'postal_locality')->exists())->toBeTrue()
        ->and($address->areaAssignments()->where('role', '0')->exists())->toBeFalse();
});
