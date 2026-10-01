<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SyncAddressAreaAssignmentsAction;
use AIArmada\Addressing\Contracts\CountryAddressProfile;
use AIArmada\Addressing\Data\AddressHierarchyDefinition;
use AIArmada\Addressing\Data\AddressLevelDefinition;
use AIArmada\Addressing\Geography\Indonesia\IndonesiaGeographyProvider;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaAssignment;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressSnapshot;
use AIArmada\Addressing\Models\City;
use AIArmada\Addressing\Models\State;
use AIArmada\Addressing\Support\AddressAreaAssignmentOwnerScope;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;

it('nulls live references and clears links when a state is deleted', function (): void {
    $country = $this->seedCountry('MY');
    $state = State::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'code' => 'SGR',
        'name' => 'Selangor',
    ]);
    $city = City::query()->create([
        'country_id' => $country->id,
        'state_id' => $state->getKey(),
        'country_code' => 'MY',
        'name' => 'Kajang',
    ]);
    $area = AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'type' => 'state',
        'level' => 1,
        'name' => 'Selangor',
        'slug' => 'selangor',
        'source' => 'test-fixture',
        'source_id' => 'fixture:selangor',
        'is_active' => true,
    ]);
    AddressAreaStateLink::query()->create([
        'address_area_id' => $area->getKey(),
        'state_id' => $state->getKey(),
        'hierarchy_type' => 'administrative',
    ]);
    $address = Address::query()->create([
        'line1' => 'Lot 12 Jalan Mawar',
        'city' => 'Kajang',
        'state' => 'Selangor',
        'state_id' => $state->getKey(),
        'country_code' => 'MY',
    ]);

    $state->delete();

    expect(State::query()->whereKey($state->getKey())->exists())->toBeFalse()
        ->and($address->fresh()->state_id)->toBeNull()
        ->and($address->fresh()->state)->toBe('Selangor')
        ->and($address->fresh()->city)->toBe('Kajang')
        ->and($city->fresh()->state_id)->toBeNull()
        ->and(AddressAreaStateLink::query()->where('state_id', $state->getKey())->exists())->toBeFalse()
        ->and(AddressArea::query()->whereKey($area->getKey())->exists())->toBeTrue();
});

it('clears address references across owners when a state is deleted', function (): void {
    $country = $this->seedCountry('MY');
    $state = State::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'code' => 'SGR',
        'name' => 'Selangor',
    ]);
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $addressA = OwnerContext::withOwner($ownerA, fn (): Address => Address::query()->create([
        'line1' => 'Owner A street',
        'state' => 'Selangor',
        'state_id' => $state->getKey(),
        'country_code' => 'MY',
    ]));
    $addressB = OwnerContext::withOwner($ownerB, fn (): Address => Address::query()->create([
        'line1' => 'Owner B street',
        'state' => 'Selangor',
        'state_id' => $state->getKey(),
        'country_code' => 'MY',
    ]));

    // Deleted inside one owner's context: reference maintenance must still
    // reach the other owner's rows instead of depending on tenancy.
    OwnerContext::withOwner($ownerA, fn (): ?bool => $state->delete());

    $freshA = OwnerContext::withOwner($ownerA, fn (): ?Address => Address::query()->whereKey($addressA->getKey())->first());
    $freshB = OwnerContext::withOwner($ownerB, fn (): ?Address => Address::query()->whereKey($addressB->getKey())->first());

    expect($freshA?->state_id)->toBeNull()
        ->and($freshA?->state)->toBe('Selangor')
        ->and($freshB?->state_id)->toBeNull()
        ->and($freshB?->state)->toBe('Selangor');
});

it('nulls city references when a city is deleted', function (): void {
    $country = $this->seedCountry('MY');
    $state = State::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'code' => 'SGR',
        'name' => 'Selangor',
    ]);
    $city = City::query()->create([
        'country_id' => $country->id,
        'state_id' => $state->getKey(),
        'country_code' => 'MY',
        'name' => 'Kajang',
    ]);
    $address = Address::query()->create([
        'line1' => 'Lot 12 Jalan Mawar',
        'city' => 'Kajang',
        'city_id' => $city->getKey(),
        'state_id' => $state->getKey(),
        'country_code' => 'MY',
    ]);

    $city->delete();

    expect(City::query()->whereKey($city->getKey())->exists())->toBeFalse()
        ->and($address->fresh()->city_id)->toBeNull()
        ->and($address->fresh()->city)->toBe('Kajang')
        ->and($address->fresh()->state_id)->toBe($state->getKey());
});

it('clears cross-owner references through a real provider cleanup and keeps later saves working', function (): void {
    $country = $this->seedCountry('ID');
    $stale = State::query()->create([
        'country_id' => $country->id,
        'country_code' => 'ID',
        'code' => 'PP',
        'name' => 'Stale Island Group',
    ]);
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $addressA = OwnerContext::withOwner($ownerA, fn (): Address => Address::query()->create([
        'line1' => 'Owner A street',
        'state' => 'Papua',
        'state_id' => $stale->getKey(),
        'country_code' => 'ID',
    ]));
    $addressB = OwnerContext::withOwner($ownerB, fn (): Address => Address::query()->create([
        'line1' => 'Owner B street',
        'state' => 'Papua',
        'state_id' => $stale->getKey(),
        'country_code' => 'ID',
    ]));

    app(IndonesiaGeographyProvider::class)->seed($country);

    expect(State::query()->where('country_id', $country->id)->where('code', 'PP')->exists())->toBeFalse();

    $freshA = OwnerContext::withOwner($ownerA, fn (): ?Address => Address::query()->whereKey($addressA->getKey())->first());
    $freshB = OwnerContext::withOwner($ownerB, fn (): ?Address => Address::query()->whereKey($addressB->getKey())->first());

    expect($freshA?->state_id)->toBeNull()
        ->and($freshB?->state_id)->toBeNull();

    // The stale pointer is gone, so a later line edit normalizes instead of
    // throwing on a dangling state reference.
    OwnerContext::withOwner($ownerA, function () use ($freshA): void {
        $freshA->line1 = 'Owner A street updated';
        $freshA->save();
    });

    expect(OwnerContext::withOwner($ownerA, fn (): ?string => Address::query()->whereKey($freshA->getKey())->value('line1')))
        ->toBe('Owner A street updated');
});

it('prunes only state-dependent assignments across owners when a state is deleted', function (): void {
    $profile = new class implements CountryAddressProfile
    {
        public function countryCode(): string
        {
            return 'MY';
        }

        public function addressHierarchies(): array
        {
            return [new AddressHierarchyDefinition('geo', 'Geo', [
                new AddressLevelDefinition(key: 'state', label: 'State', kind: 'state'),
                new AddressLevelDefinition(key: 'district', label: 'District', kind: 'area', parentKey: 'state', assignmentRole: 'test_district', areaTypes: ['district'], areaLevel: 2),
                new AddressLevelDefinition(key: 'zone', label: 'Zone', kind: 'area', assignmentRole: 'test_zone', areaTypes: ['zone'], areaLevel: 2),
            ])];
        }
    };
    config()->set('addressing.geography.providers', [get_class($profile)]);

    $country = $this->seedCountry('MY');
    $state = State::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'code' => 'SGR',
        'name' => 'Selangor',
    ]);

    $makeArea = static fn (string $type, string $name, string $slug): AddressArea => AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'type' => $type,
        'level' => $type === 'state' ? 1 : 2,
        'name' => $name,
        'slug' => $slug,
        'source' => 'test-fixture',
        'source_id' => 'fixture:' . $slug,
        'is_active' => true,
    ]);

    $stateArea = $makeArea('state', 'Selangor', 'selangor');
    $district = $makeArea('district', 'Petaling', 'petaling');
    $zone = $makeArea('zone', 'Zone One', 'zone-one');

    AddressAreaStateLink::query()->create([
        'address_area_id' => $stateArea->getKey(),
        'state_id' => $state->getKey(),
    ]);
    AddressAreaRelationship::query()->create([
        'parent_address_area_id' => $stateArea->getKey(),
        'child_address_area_id' => $district->getKey(),
        'relationship_type' => 'contains',
        'hierarchy_type' => 'geo',
    ]);

    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $sync = static fn (): array => [
        'test_district' => (string) AddressArea::query()->where('slug', 'petaling')->value('id'),
        'test_zone' => (string) AddressArea::query()->where('slug', 'zone-one')->value('id'),
    ];

    $addressA = OwnerContext::withOwner($ownerA, fn (): Address => Address::query()->create([
        'line1' => 'Owner A street',
        'state' => 'Selangor',
        'state_id' => $state->getKey(),
        'country_code' => 'MY',
    ]));
    $addressB = OwnerContext::withOwner($ownerB, fn (): Address => Address::query()->create([
        'line1' => 'Owner B street',
        'state' => 'Selangor',
        'state_id' => $state->getKey(),
        'country_code' => 'MY',
    ]));

    OwnerContext::withOwner($ownerA, fn (): mixed => app(SyncAddressAreaAssignmentsAction::class)->execute($addressA, $sync()));
    OwnerContext::withOwner($ownerB, fn (): mixed => app(SyncAddressAreaAssignmentsAction::class)->execute($addressB, $sync()));

    // The per-save hook prunes the same way: nulling through a model save
    // drops the state-parented district but keeps the parentless zone.
    $addressC = OwnerContext::withOwner($ownerA, fn (): Address => Address::query()->create([
        'line1' => 'Owner C street',
        'state' => 'Selangor',
        'state_id' => $state->getKey(),
        'country_code' => 'MY',
    ]));
    OwnerContext::withOwner($ownerA, fn (): mixed => app(SyncAddressAreaAssignmentsAction::class)->execute($addressC, $sync()));
    OwnerContext::withOwner($ownerA, function () use ($addressC): void {
        // Null through a model save: free-text must not re-resolve to the
        // still-existing State row, so use a non-matching name.
        $addressC->state_id = null;
        $addressC->state = 'Deleted State';
        $addressC->save();
    });

    expect(OwnerContext::withOwner($ownerA, fn (): array => AddressAreaAssignment::query()->where('address_id', $addressC->getKey())->pluck('role')->all()))
        ->toEqualCanonicalizing(['test_zone'])
        ->and(OwnerContext::withOwner($ownerA, fn (): ?string => Address::query()->whereKey($addressC->getKey())->value('state')))
        ->toBe('Deleted State');

    OwnerContext::withOwner($ownerA, fn (): ?bool => $state->delete());

    foreach ([[$ownerA, $addressA], [$ownerB, $addressB]] as [$owner, $address]) {
        $fresh = OwnerContext::withOwner($owner, fn (): ?Address => Address::query()->whereKey($address->getKey())->first());
        $roles = OwnerContext::withOwner($owner, fn (): array => AddressAreaAssignment::query()->where('address_id', $address->getKey())->pluck('role')->all());

        expect($fresh?->state_id)->toBeNull()
            ->and($fresh?->state)->toBe('Selangor')
            ->and($roles)->toEqualCanonicalizing(['test_zone']);
    }
});

it('prunes assignments cross-owner without owner resolution when owner mode is disabled at runtime', function (): void {
    $profile = new class implements CountryAddressProfile
    {
        public function countryCode(): string
        {
            return 'MY';
        }

        public function addressHierarchies(): array
        {
            return [new AddressHierarchyDefinition('geo', 'Geo', [
                new AddressLevelDefinition(key: 'state', label: 'State', kind: 'state'),
                new AddressLevelDefinition(key: 'district', label: 'District', kind: 'area', parentKey: 'state', assignmentRole: 'test_district', areaTypes: ['district'], areaLevel: 2),
                new AddressLevelDefinition(key: 'zone', label: 'Zone', kind: 'area', assignmentRole: 'test_zone', areaTypes: ['zone'], areaLevel: 2),
            ])];
        }
    };
    config()->set('addressing.geography.providers', [get_class($profile)]);

    $country = $this->seedCountry('MY');
    $state = State::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'code' => 'SGR',
        'name' => 'Selangor',
    ]);

    $makeArea = static fn (string $type, string $name, string $slug): AddressArea => AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'type' => $type,
        'level' => $type === 'state' ? 1 : 2,
        'name' => $name,
        'slug' => $slug,
        'source' => 'test-fixture',
        'source_id' => 'fixture:' . $slug,
        'is_active' => true,
    ]);

    $stateArea = $makeArea('state', 'Selangor', 'selangor-disabled');
    $district = $makeArea('district', 'Petaling', 'petaling-disabled');
    $zone = $makeArea('zone', 'Zone One', 'zone-one-disabled');

    AddressAreaStateLink::query()->create([
        'address_area_id' => $stateArea->getKey(),
        'state_id' => $state->getKey(),
    ]);
    AddressAreaRelationship::query()->create([
        'parent_address_area_id' => $stateArea->getKey(),
        'child_address_area_id' => $district->getKey(),
        'relationship_type' => 'contains',
        'hierarchy_type' => 'geo',
    ]);

    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $assignments = [
        'test_district' => (string) $district->getKey(),
        'test_zone' => (string) $zone->getKey(),
    ];

    $addressA = OwnerContext::withOwner($ownerA, fn (): Address => Address::query()->create([
        'line1' => 'Owner A street',
        'state' => 'Selangor',
        'state_id' => $state->getKey(),
        'country_code' => 'MY',
    ]));
    $addressB = OwnerContext::withOwner($ownerB, fn (): Address => Address::query()->create([
        'line1' => 'Owner B street',
        'state' => 'Selangor',
        'state_id' => $state->getKey(),
        'country_code' => 'MY',
    ]));

    OwnerContext::withOwner($ownerA, fn (): mixed => app(SyncAddressAreaAssignmentsAction::class)->execute($addressA, $assignments));
    OwnerContext::withOwner($ownerB, fn (): mixed => app(SyncAddressAreaAssignmentsAction::class)->execute($addressB, $assignments));

    $snapshot = OwnerContext::withOwner($ownerA, fn (): AddressSnapshot => AddressSnapshot::query()->create([
        'address_id' => $addressA->getKey(),
        'snapshotable_type' => $ownerA->getMorphClass(),
        'snapshotable_id' => $ownerA->getKey(),
        'reason' => 'shipment',
        'state' => 'Selangor',
        'country_code' => 'MY',
    ]));

    // Models booted while owner mode was enabled; flipping at runtime must
    // not let the stale booted scope hide cross-owner rows from cleanup.
    config()->set('addressing.features.owner.enabled', false);

    $state->delete();

    foreach ([$addressA, $addressB] as $address) {
        $fresh = Address::query()->withoutOwnerScope()->whereKey($address->getKey())->firstOrFail();
        $roles = AddressAreaAssignment::query()
            ->withoutGlobalScope(AddressAreaAssignmentOwnerScope::class)
            ->where('address_id', $address->getKey())
            ->pluck('role')
            ->all();

        expect($fresh->state_id)->toBeNull()
            ->and($fresh->state)->toBe('Selangor')
            ->and($roles)->toEqualCanonicalizing(['test_zone']);
    }

    // Frozen snapshots survive reference cleanup untouched.
    $frozen = AddressSnapshot::query()->withoutOwnerScope()->whereKey($snapshot->getKey())->firstOrFail();

    expect($frozen->address_id)->toBe($addressA->getKey())
        ->and($frozen->state)->toBe('Selangor');
});
