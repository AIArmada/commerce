<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressLocationData;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Support\AddressLocationScope;
use AIArmada\Addressing\Traits\HasAddresses;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

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

    $this->location = [
        'country_id' => (string) Str::uuid(),
        'state_id' => (string) Str::uuid(),
        'city_id' => (string) Str::uuid(),
        'admin_area_1_id' => (string) Str::uuid(),
        'admin_area_2_id' => (string) Str::uuid(),
        'admin_area_3_id' => (string) Str::uuid(),
        'admin_area_4_id' => (string) Str::uuid(),
    ];

    $matchingAddress = Address::query()->create([
        'line1' => 'Matching address',
        'country_code' => 'MY',
        ...$this->location,
    ]);
    $this->owner->attachAddress($matchingAddress, isPrimary: true);

    $otherAddress = Address::query()->create([
        'line1' => 'Other address',
        'country_code' => 'MY',
        ...array_map(static fn (): string => (string) Str::uuid(), $this->location),
    ]);
    $this->otherOwner->attachAddress($otherAddress, isPrimary: true);
});

it('normalizes only non-empty canonical location criteria', function (): void {
    $location = AddressLocationData::fromArray([
        'country_id' => '  country-id  ',
        'state_id' => '',
        'city_id' => 123,
    ]);

    expect($location->criteria())->toBe(['country_id' => 'country-id'])
        ->and($location->isEmpty())->toBeFalse()
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
    'first area' => 'admin_area_1_id',
    'second area' => 'admin_area_2_id',
    'third area' => 'admin_area_3_id',
    'fourth area' => 'admin_area_4_id',
]);

it('combines canonical location criteria without changing empty queries', function (): void {
    $scope = app(AddressLocationScope::class);

    $matchingIds = $scope
        ->apply($this->owner::query(), AddressLocationData::fromArray($this->location))
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
