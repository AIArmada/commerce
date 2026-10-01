<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\ImportAddressAreasAction;
use AIArmada\Addressing\Actions\SyncAddressAreaAssignmentsAction;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seedCountry('MY');
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
        new AddressAreaData(source: 'areas', sourceId: 'subdivision', countryCode: 'MY', type: 'mukim', level: 3, name: 'Subdivision', parentSourceId: 'admin', hierarchyType: 'administrative'),
    ]));

    $stateArea = AddressArea::query()->where('source_id', 'state')->firstOrFail();
    AddressAreaStateLink::query()->create([
        'address_area_id' => $stateArea->getKey(),
        'state_id' => $state->getKey(),
    ]);
    $this->state = $state;
});

it('validates against persisted country when the caller mutated country without saving', function (): void {
    $address = Address::query()->create([
        'country_code' => 'MY',
        'country' => 'Malaysia',
        'state_id' => $this->state->getKey(),
    ]);
    $postal = AddressArea::query()->where('source_id', 'postal')->firstOrFail();

    $address->country_code = 'SG';

    app(SyncAddressAreaAssignmentsAction::class)->execute($address, [
        'postal_locality' => $postal->getKey(),
    ]);

    expect($address->areaAssignments()->where('role', 'postal_locality')->exists())->toBeTrue()
        ->and($address->fresh()?->country_code)->toBe('MY')
        ->and($address->isDirty('country_code'))->toBeTrue();
});

it('rejects areas that are invalid under the persisted country of a stale model', function (): void {
    $address = Address::query()->create([
        'country_code' => 'MY',
        'country' => 'Malaysia',
        'state_id' => $this->state->getKey(),
    ]);
    $postal = AddressArea::query()->where('source_id', 'postal')->firstOrFail();

    Address::query()->whereKey($address->getKey())->update(['country_code' => 'SG']);

    expect($address->country_code)->toBe('MY');

    expect(fn (): mixed => app(SyncAddressAreaAssignmentsAction::class)->execute($address, [
        'postal_locality' => $postal->getKey(),
    ]))->toThrow(ValidationException::class);

    expect($address->areaAssignments()->exists())->toBeFalse();
});

it('rejects areas that are invalid under the persisted state of a stale model', function (): void {
    $address = Address::query()->create([
        'country_code' => 'MY',
        'country' => 'Malaysia',
        'state_id' => $this->state->getKey(),
    ]);
    $subdivision = AddressArea::query()->where('source_id', 'subdivision')->firstOrFail();

    $otherState = State::query()->create([
        'country_id' => $this->country->getKey(),
        'country_code' => 'MY',
        'code' => '02',
        'name' => 'Kedah',
    ]);

    Address::query()->whereKey($address->getKey())->update(['state_id' => $otherState->getKey()]);

    expect($address->state_id)->toBe($this->state->getKey());

    expect(fn (): mixed => app(SyncAddressAreaAssignmentsAction::class)->execute($address, [
        'administrative_subdivision' => $subdivision->getKey(),
    ]))->toThrow(ValidationException::class);

    expect($address->areaAssignments()->exists())->toBeFalse();
});

it('ignores an unsaved state change when validating hierarchy', function (): void {
    $address = Address::query()->create([
        'country_code' => 'MY',
        'country' => 'Malaysia',
        'state_id' => $this->state->getKey(),
    ]);
    $subdivision = AddressArea::query()->where('source_id', 'subdivision')->firstOrFail();

    $otherState = State::query()->create([
        'country_id' => $this->country->getKey(),
        'country_code' => 'MY',
        'code' => '03',
        'name' => 'Melaka',
    ]);
    $address->state_id = $otherState->getKey();

    app(SyncAddressAreaAssignmentsAction::class)->execute($address, [
        'administrative_subdivision' => $subdivision->getKey(),
    ]);

    expect($address->areaAssignments()->where('role', 'administrative_subdivision')->exists())->toBeTrue()
        ->and($address->fresh()?->state_id)->toBe($this->state->getKey());
});

it('rejects an explicit state that contradicts the persisted address state', function (): void {
    $stateB = State::query()->create([
        'country_id' => $this->country->getKey(),
        'country_code' => 'MY',
        'code' => '02',
        'name' => 'Kedah',
    ]);

    app(ImportAddressAreasAction::class)->execute(new ArrayAddressAreaSource('areas-b', [
        new AddressAreaData(source: 'areas-b', sourceId: 'state-b', countryCode: 'MY', type: 'state', level: 1, name: 'Kedah'),
        new AddressAreaData(source: 'areas-b', sourceId: 'postal-b', countryCode: 'MY', type: 'locality', level: 2, name: 'Alor Setar', parentSourceId: 'state-b', hierarchyType: 'postal'),
    ]));

    $stateAreaB = AddressArea::query()->where('source_id', 'state-b')->firstOrFail();
    $postalB = AddressArea::query()->where('source_id', 'postal-b')->firstOrFail();

    AddressAreaStateLink::query()->create([
        'address_area_id' => $stateAreaB->getKey(),
        'state_id' => $stateB->getKey(),
    ]);

    $address = Address::query()->create([
        'country_code' => 'MY',
        'country' => 'Malaysia',
        'state_id' => $this->state->getKey(),
    ]);

    $address->country_code = 'SG';
    $address->state_id = $stateB->getKey();

    try {
        app(SyncAddressAreaAssignmentsAction::class)->execute(
            $address,
            ['postal_locality' => $postalB->getKey()],
            $stateB->getKey(),
        );

        $this->fail('Expected a ValidationException for a contradictory explicit state.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('state_id');
    }

    expect($address->areaAssignments()->exists())->toBeFalse()
        ->and($address->fresh()?->state_id)->toBe($this->state->getKey())
        ->and($address->fresh()?->country_code)->toBe('MY');
});

it('accepts an explicit state matching the persisted state despite unsaved caller mutations', function (): void {
    $address = Address::query()->create([
        'country_code' => 'MY',
        'country' => 'Malaysia',
        'state_id' => $this->state->getKey(),
    ]);
    $postal = AddressArea::query()->where('source_id', 'postal')->firstOrFail();

    $otherState = State::query()->create([
        'country_id' => $this->country->getKey(),
        'country_code' => 'MY',
        'code' => '04',
        'name' => 'Perak',
    ]);

    $address->country_code = 'SG';
    $address->state_id = $otherState->getKey();

    app(SyncAddressAreaAssignmentsAction::class)->execute(
        $address,
        ['postal_locality' => $postal->getKey()],
        $this->state->getKey(),
    );

    expect($address->areaAssignments()->where('role', 'postal_locality')->exists())->toBeTrue()
        ->and($address->fresh()?->state_id)->toBe($this->state->getKey())
        ->and($address->fresh()?->country_code)->toBe('MY');
});
