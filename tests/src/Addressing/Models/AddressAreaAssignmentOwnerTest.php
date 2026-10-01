<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaAssignment;
use AIArmada\Addressing\Support\AddressAreaAssignmentOwnerScope;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

beforeEach(function (): void {
    $this->ownerA = User::factory()->create();
    $this->ownerB = User::factory()->create();

    $this->area = AddressArea::query()->create([
        'country_code' => 'MY',
        'type' => 'locality',
        'level' => 2,
        'name' => 'Bangsar',
        'slug' => 'bangsar',
        'source' => 'tests',
        'source_id' => 'bangsar',
    ]);

    $this->addressA = OwnerContext::withOwner($this->ownerA, fn (): Address => Address::query()->create([
        'line1' => 'Owner A address',
        'country_code' => 'MY',
    ]));

    $this->assignmentA = OwnerContext::withOwner($this->ownerA, fn (): AddressAreaAssignment => AddressAreaAssignment::query()->create([
        'address_id' => $this->addressA->getKey(),
        'address_area_id' => $this->area->getKey(),
        'role' => 'postal_locality',
        'is_primary' => true,
    ]));
});

it('isolates assignment reads to the owner of the parent address', function (): void {
    expect(OwnerContext::withOwner($this->ownerA, fn (): int => AddressAreaAssignment::query()->count()))
        ->toBe(1)
        ->and(OwnerContext::withOwner($this->ownerB, fn (): int => AddressAreaAssignment::query()->count()))
        ->toBe(0)
        ->and(OwnerContext::withOwner($this->ownerB, fn (): ?AddressAreaAssignment => AddressAreaAssignment::query()->whereKey($this->assignmentA->getKey())->first()))
        ->toBeNull();
});

it('forbids creating assignments for another owner address', function (): void {
    expect(fn (): AddressAreaAssignment => OwnerContext::withOwner($this->ownerB, fn (): AddressAreaAssignment => AddressAreaAssignment::query()->create([
        'address_id' => $this->addressA->getKey(),
        'address_area_id' => $this->area->getKey(),
        'role' => 'postal_locality',
        'is_primary' => true,
    ])))->toThrow(AuthorizationException::class);
});

it('forbids updating assignments of another owner address', function (): void {
    $assignment = $this->assignmentA;

    expect(fn (): mixed => OwnerContext::withOwner($this->ownerB, fn (): mixed => $assignment->update(['is_primary' => false])))
        ->toThrow(AuthorizationException::class);

    expect($assignment->fresh()->is_primary)->toBeTrue();
});

it('forbids deleting assignments of another owner address', function (): void {
    $assignment = $this->assignmentA;

    expect(fn (): mixed => OwnerContext::withOwner($this->ownerB, fn (): mixed => $assignment->delete()))
        ->toThrow(AuthorizationException::class);

    expect(AddressAreaAssignment::query()->withoutGlobalScope(AddressAreaAssignmentOwnerScope::class)->whereKey($assignment->getKey())->exists())->toBeTrue();
});

it('forbids reassigning an assignment to another address', function (): void {
    $addressB = OwnerContext::withOwner($this->ownerB, fn (): Address => Address::query()->create([
        'line1' => 'Owner B address',
        'country_code' => 'MY',
    ]));

    expect(fn (): mixed => OwnerContext::withOwner($this->ownerB, fn (): mixed => $this->assignmentA->update(['address_id' => $addressB->getKey()])))
        ->toThrow(AuthorizationException::class);
});

it('scopes global-address assignments to explicit global context', function (): void {
    $globalAddress = OwnerContext::withOwner(null, fn (): Address => Address::query()->create([
        'line1' => 'Global address',
        'country_code' => 'MY',
    ]));
    $globalAssignment = OwnerContext::withOwner(null, fn (): AddressAreaAssignment => AddressAreaAssignment::query()->create([
        'address_id' => $globalAddress->getKey(),
        'address_area_id' => $this->area->getKey(),
        'role' => 'postal_locality',
        'is_primary' => true,
    ]));

    expect(OwnerContext::withOwner($this->ownerA, fn (): bool => AddressAreaAssignment::query()->whereKey($globalAssignment->getKey())->exists()))
        ->toBeFalse()
        ->and(OwnerContext::withOwner(null, fn (): bool => AddressAreaAssignment::query()->whereKey($globalAssignment->getKey())->exists()))
        ->toBeTrue()
        ->and(OwnerContext::withOwner(null, fn (): bool => AddressAreaAssignment::query()->whereKey($this->assignmentA->getKey())->exists()))
        ->toBeFalse();
});

it('rejects assignments for unknown addresses', function (): void {
    expect(fn (): AddressAreaAssignment => OwnerContext::withOwner($this->ownerA, fn (): AddressAreaAssignment => AddressAreaAssignment::query()->create([
        'address_id' => (string) Str::orderedUuid(),
        'address_area_id' => $this->area->getKey(),
        'role' => 'postal_locality',
        'is_primary' => true,
    ])))->toThrow(AuthorizationException::class);
});

it('lifts assignment isolation when owner enforcement is disabled', function (): void {
    config()->set('addressing.features.owner.enabled', false);

    expect(OwnerContext::withOwner($this->ownerB, fn (): int => AddressAreaAssignment::query()->count()))
        ->toBe(1);

    $assignment = OwnerContext::withOwner($this->ownerB, fn (): AddressAreaAssignment => AddressAreaAssignment::query()->create([
        'address_id' => $this->addressA->getKey(),
        'address_area_id' => $this->area->getKey(),
        'role' => 'administrative_district',
        'is_primary' => true,
    ]));

    expect($assignment->exists)->toBeTrue();
});

it('respects additional visibility scopes on the configured address model', function (): void {
    $hiddenAddress = OwnerContext::withOwner($this->ownerA, fn (): Address => Address::query()->create([
        'line1' => 'Hidden',
        'country_code' => 'MY',
    ]));

    $hiddenAssignment = OwnerContext::withOwner($this->ownerA, fn (): AddressAreaAssignment => AddressAreaAssignment::query()->create([
        'address_id' => $hiddenAddress->getKey(),
        'address_area_id' => $this->area->getKey(),
        'role' => 'administrative_district',
        'is_primary' => true,
    ]));

    $scopedAddress = new class extends Address
    {
        protected static function booted(): void
        {
            parent::booted();

            static::addGlobalScope('test_visibility', function (Builder $query): void {
                $query->where('line1', '!=', 'Hidden');
            });
        }
    };

    config()->set('addressing.models.address', $scopedAddress::class);

    $visibleKeys = OwnerContext::withOwner($this->ownerA, fn (): array => AddressAreaAssignment::query()->pluck('id')->all());

    expect($visibleKeys)->toContain($this->assignmentA->getKey())
        ->and($visibleKeys)->not->toContain($hiddenAssignment->getKey());

    $addressKeys = OwnerContext::withOwner($this->ownerA, fn () => $scopedAddress::query()->pluck('id')->all());

    expect($addressKeys)->toContain($this->addressA->getKey())
        ->and($addressKeys)->not->toContain($hiddenAddress->getKey());
});
