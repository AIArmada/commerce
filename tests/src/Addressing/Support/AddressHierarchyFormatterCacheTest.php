<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\CountryDatasetSeeder;
use AIArmada\Addressing\Features\AddressLifecycle\Models\Address;
use AIArmada\Addressing\Features\AddressLifecycle\Models\AddressAreaAssignment;
use AIArmada\Addressing\Features\AddressLifecycle\Support\AddressHierarchyFormatter;
use AIArmada\Addressing\Features\Geography\Models\AddressArea;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    app(CountryDatasetSeeder::class)->execute();

    $this->address = OwnerContext::withOwner(null, fn (): Address => Address::query()->create([
        'line1' => '1 Jalan Ilmu',
        'country_code' => 'MY',
    ]));

    $this->subdivision = AddressArea::query()->create([
        'country_code' => 'MY', 'type' => 'mukim', 'level' => 3,
        'name' => 'Damansara', 'slug' => 'damansara', 'source' => 'areas', 'source_id' => 'damansara',
    ]);

    $this->locality = AddressArea::query()->create([
        'country_code' => 'MY', 'type' => 'locality', 'level' => 4,
        'name' => 'Kuala Lumpur', 'slug' => 'kuala-lumpur', 'source' => 'areas', 'source_id' => 'kuala-lumpur',
    ]);

    OwnerContext::withOwner(null, function (): void {
        AddressAreaAssignment::query()->create([
            'address_id' => $this->address->getKey(),
            'address_area_id' => $this->subdivision->getKey(),
            'role' => 'administrative_subdivision',
            'is_primary' => true,
        ]);

        AddressAreaAssignment::query()->create([
            'address_id' => $this->address->getKey(),
            'address_area_id' => $this->locality->getKey(),
            'role' => 'postal_locality',
            'is_primary' => true,
        ]);
    });
});

it('reuses loaded assignments and areas for subdivision and locality names', function (): void {
    $address = $this->address->fresh();
    $queries = [];

    DB::listen(static function (QueryExecuted $query) use (&$queries): void {
        $queries[] = mb_strtolower($query->sql);
    });

    $name = OwnerContext::withOwner(null, fn (): ?string => AddressHierarchyFormatter::subdivisionOrLocalityName($address));

    expect($name)->toBe('Damansara');

    $assignmentQueries = collect($queries)->filter(static fn (string $sql): bool => str_contains($sql, 'address_area_assignments'));
    $areaQueries = collect($queries)->filter(static fn (string $sql): bool => str_contains($sql, 'address_areas'));

    expect($assignmentQueries)->toHaveCount(1)
        ->and($areaQueries)->toHaveCount(1);
});

it('keeps results identical when callers preload a constrained assignment relation', function (): void {
    $address = $this->address->fresh();

    OwnerContext::withOwner(null, function () use ($address): void {
        $address->load(['areaAssignments' => static fn ($query) => $query->where('role', 'postal_locality')]);

        expect(AddressHierarchyFormatter::subdivisionOrLocalityName($address))->toBe('Damansara')
            ->and(AddressHierarchyFormatter::roleAreaName($address, 'postal_locality'))->toBe('Kuala Lumpur');
    });
});
