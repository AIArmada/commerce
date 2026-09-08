<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressLocationData;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaAssignment;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\City;
use AIArmada\Addressing\Models\State;
use AIArmada\Addressing\Support\AddressLocationScope;
use AIArmada\Addressing\Traits\HasAddresses;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

beforeEach(function (): void {
    $this->owner = new class extends Model
    {
        use HasAddresses;

        protected $table = 'test_owners';

        /** @return MorphToMany<Address, $this> */
        public function publicAddresses(): MorphToMany
        {
            return $this->addresses();
        }
    };
    $this->owner->save();

    $this->otherOwner = new ($this->owner::class);
    $this->otherOwner->save();

    $country = AddressCountry::query()->create([
        'iso2' => 'MY',
        'name' => 'Malaysia',
    ]);
    $state = State::query()->create([
        'country_id' => $country->getKey(),
        'name' => 'Selangor',
    ]);
    $city = City::query()->create([
        'country_id' => $country->getKey(),
        'state_id' => $state->getKey(),
        'name' => 'Shah Alam',
    ]);
    $this->location = [
        'country_id' => $country->getKey(),
        'state_id' => $state->getKey(),
        'city_id' => $city->getKey(),
    ];

    $otherCountry = AddressCountry::query()->create([
        'iso2' => 'SG',
        'name' => 'Singapore',
    ]);
    $otherState = State::query()->create([
        'country_id' => $otherCountry->getKey(),
        'name' => 'Singapore',
    ]);
    $otherCity = City::query()->create([
        'country_id' => $otherCountry->getKey(),
        'state_id' => $otherState->getKey(),
        'name' => 'Singapore',
    ]);
    $this->otherLocation = [
        'country_id' => $otherCountry->getKey(),
        'state_id' => $otherState->getKey(),
        'city_id' => $otherCity->getKey(),
    ];

    $this->matchingArea = AddressArea::query()->create([
        'country_code' => 'MY',
        'type' => 'locality',
        'level' => 2,
        'name' => 'Matching area',
        'slug' => 'matching-area',
        'source' => 'tests',
        'source_id' => 'matching',
    ]);
    $this->otherArea = AddressArea::query()->create([
        'country_code' => 'MY',
        'type' => 'locality',
        'level' => 2,
        'name' => 'Other area',
        'slug' => 'other-area',
        'source' => 'tests',
        'source_id' => 'other',
    ]);

    $matchingAddress = Address::query()->create([
        'line1' => 'Matching address',
        'country_code' => 'MY',
        ...$this->location,
    ]);
    AddressAreaAssignment::query()->create([
        'address_id' => $matchingAddress->getKey(),
        'address_area_id' => $this->matchingArea->getKey(),
        'role' => 'postal_locality',
        'is_primary' => true,
    ]);
    $this->owner->attachAddress($matchingAddress, isPrimary: true);

    $otherAddress = Address::query()->create([
        'line1' => 'Other address',
        'country_code' => 'SG',
        ...$this->otherLocation,
    ]);
    AddressAreaAssignment::query()->create([
        'address_id' => $otherAddress->getKey(),
        'address_area_id' => $this->otherArea->getKey(),
        'role' => 'postal_locality',
        'is_primary' => true,
    ]);
    $this->otherOwner->attachAddress($otherAddress, isPrimary: true);
});

it('normalizes only non-empty canonical location criteria', function (): void {
    $location = AddressLocationData::fromArray([
        'country_id' => '  country-id  ',
        'state_id' => '',
        'city_id' => 123,
        'area_assignments' => ['postal_locality' => ' area-id '],
    ]);

    expect($location->criteria())->toBe(['country_id' => 'country-id'])
        ->and($location->assignments())->toBe(['postal_locality' => 'area-id'])
        ->and($location->isEmpty())->toBeFalse()
        ->and(new AddressLocationData(areaAssignments: ['postal_locality' => 'area-id'])->isEmpty())->toBeFalse()
        ->and(AddressLocationData::fromArray([])->isEmpty())->toBeTrue();
});

it('filters addressable models by every canonical location column', function (string $column): void {
    $location = AddressLocationData::fromArray([$column => $this->location[$column]]);

    $ownerIds = app(AddressLocationScope::class)
        ->apply($this->owner::query(), $location)
        ->pluck('id')
        ->all();

    expect($ownerIds)->toBe([$this->owner->id]);
})->with([
    'country' => 'country_id',
    'state' => 'state_id',
    'city' => 'city_id',
]);

it('filters addressable models by typed area assignments', function (): void {
    $ownerIds = app(AddressLocationScope::class)
        ->apply($this->owner::query(), new AddressLocationData(
            areaAssignments: ['postal_locality' => $this->matchingArea->getKey()],
        ))
        ->pluck('id')
        ->all();

    expect($ownerIds)->toBe([$this->owner->id]);
});

it('combines canonical location criteria without changing empty queries', function (): void {
    $location = new AddressLocationData(
        countryId: $this->location['country_id'],
        areaAssignments: ['postal_locality' => $this->matchingArea->getKey()],
    );
    $scope = app(AddressLocationScope::class);

    $matchingIds = $scope
        ->apply($this->owner::query(), $location)
        ->pluck('id')
        ->all();
    $allIds = $scope
        ->apply($this->owner::query(), new AddressLocationData)
        ->orderBy('id')
        ->pluck('id')
        ->all();

    expect($matchingIds)->toBe([$this->owner->id])
        ->and($allIds)->toContain($this->owner->id, $this->otherOwner->id);
});

it('uses a supplied address relation name', function (): void {
    $ownerIds = app(AddressLocationScope::class)
        ->apply(
            $this->owner::query(),
            AddressLocationData::fromArray(['state_id' => $this->location['state_id']]),
            relation: 'publicAddresses',
        )
        ->pluck('id')
        ->all();

    expect($ownerIds)->toBe([$this->owner->id]);
});
