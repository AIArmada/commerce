<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SavePostalCodeAction;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaPostalCode;
use AIArmada\Addressing\Models\PostalCode;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seedCountry('MY');
    $this->seedCountry('SG');
});

it('normalizes postcode identity and links validated manual coverage', function (): void {
    $area = AddressArea::query()->create([
        'country_code' => 'MY', 'type' => 'locality', 'level' => 1, 'name' => 'Bangsar',
        'slug' => 'bangsar', 'source' => 'areas', 'source_id' => 'bangsar',
    ]);

    $postcode = app(SavePostalCodeAction::class)->execute([
        'country_code' => 'my',
        'code' => ' 50450 ',
        'metadata' => ['owner' => 'admin'],
        'manual_coverage' => [[
            'area_id' => $area->getKey(),
            'relationship_type' => 'served_by',
            'is_primary' => true,
        ]],
    ]);

    $link = AddressAreaPostalCode::query()->where('postal_code_id', $postcode->getKey())->firstOrFail();

    expect($postcode->country_code)->toBe('MY')
        ->and($postcode->code)->toBe('50450')
        ->and($postcode->is_active)->toBeTrue()
        ->and($postcode->metadata)->toBe(['owner' => 'admin'])
        ->and($link->source)->toBe('manual')
        ->and($link->address_area_id)->toBe($area->getKey())
        ->and($link->is_primary)->toBeTrue();
});

it('preserves provider and inactive manual coverage when manual coverage is omitted', function (): void {
    $providerArea = AddressArea::query()->create([
        'country_code' => 'MY', 'type' => 'locality', 'level' => 1, 'name' => 'Provider Area',
        'slug' => 'provider-area', 'source' => 'areas', 'source_id' => 'provider',
    ]);
    $manualArea = AddressArea::query()->create([
        'country_code' => 'MY', 'type' => 'locality', 'level' => 1, 'name' => 'Retired Area',
        'slug' => 'retired-area', 'source' => 'areas', 'source_id' => 'retired', 'is_active' => false,
    ]);
    $postcode = PostalCode::query()->create([
        'country_code' => 'MY', 'code' => '50450', 'is_active' => true, 'metadata' => ['existing' => true],
    ]);
    AddressAreaPostalCode::query()->create([
        'postal_code_id' => $postcode->getKey(), 'address_area_id' => $providerArea->getKey(),
        'source' => 'provider', 'source_id' => 'feed-1', 'relationship_type' => 'served_by', 'is_primary' => true,
    ]);
    AddressAreaPostalCode::query()->create([
        'postal_code_id' => $postcode->getKey(), 'address_area_id' => $manualArea->getKey(),
        'source' => 'manual', 'source_id' => 'retained-source-id', 'relationship_type' => 'served_by', 'is_primary' => false,
    ]);

    app(SavePostalCodeAction::class)->execute(['is_active' => false], $postcode);

    expect($postcode->fresh()->is_active)->toBeFalse()
        ->and($postcode->fresh()->metadata)->toBe(['existing' => true])
        ->and(AddressAreaPostalCode::query()->where('postal_code_id', $postcode->getKey())->count())->toBe(2)
        ->and(AddressAreaPostalCode::query()->where('source', 'manual')->value('source_id'))->toBe('retained-source-id');
});

it('allows an unchanged inactive manual edge but rejects new inactive or cross-country coverage', function (): void {
    $inactive = AddressArea::query()->create([
        'country_code' => 'MY', 'type' => 'locality', 'level' => 1, 'name' => 'Retired Area',
        'slug' => 'retired-area', 'source' => 'areas', 'source_id' => 'retired', 'is_active' => false,
    ]);
    $foreign = AddressArea::query()->create([
        'country_code' => 'SG', 'type' => 'locality', 'level' => 1, 'name' => 'Singapore Area',
        'slug' => 'singapore-area', 'source' => 'areas', 'source_id' => 'singapore',
    ]);
    $postcode = app(SavePostalCodeAction::class)->execute(['country_code' => 'MY', 'code' => '50450']);

    expect(fn () => app(SavePostalCodeAction::class)->execute([
        'manual_coverage' => [[
            'area_id' => $inactive->getKey(),
            'relationship_type' => 'served_by',
            'is_primary' => false,
        ]],
    ], $postcode))->toThrow(ValidationException::class, 'New manual postcode coverage')
        ->and(fn () => app(SavePostalCodeAction::class)->execute([
            'manual_coverage' => [[
                'area_id' => $foreign->getKey(),
                'relationship_type' => 'served_by',
                'is_primary' => false,
            ]],
        ], $postcode))->toThrow(ValidationException::class, 'New manual postcode coverage');

    AddressAreaPostalCode::query()->create([
        'postal_code_id' => $postcode->getKey(), 'address_area_id' => $inactive->getKey(),
        'source' => 'manual', 'source_id' => null, 'relationship_type' => 'served_by', 'is_primary' => false,
    ]);

    app(SavePostalCodeAction::class)->execute([
        'manual_coverage' => [[
            'area_id' => $inactive->getKey(),
            'relationship_type' => 'served_by',
            'is_primary' => true,
        ]],
    ], $postcode);

    expect(AddressAreaPostalCode::query()->where('postal_code_id', $postcode->getKey())->value('is_primary'))->toBeTrue();
});

it('keeps country and postcode identity immutable during updates with coverage', function (): void {
    $area = AddressArea::query()->create([
        'country_code' => 'MY', 'type' => 'locality', 'level' => 1, 'name' => 'Bangsar',
        'slug' => 'bangsar', 'source' => 'areas', 'source_id' => 'bangsar',
    ]);
    $postcode = PostalCode::query()->create(['country_code' => 'MY', 'code' => '50450']);
    AddressAreaPostalCode::query()->create([
        'postal_code_id' => $postcode->getKey(), 'address_area_id' => $area->getKey(),
        'source' => 'provider', 'source_id' => 'provider-1', 'relationship_type' => 'served_by', 'is_primary' => true,
    ]);

    app(SavePostalCodeAction::class)->execute([
        'country_code' => 'SG',
        'code' => '99999',
        'is_active' => false,
    ], $postcode);

    expect($postcode->fresh()->country_code)->toBe('MY')
        ->and($postcode->fresh()->code)->toBe('50450')
        ->and($postcode->fresh()->is_active)->toBeFalse();
});

it('locks selected manual coverage areas in primary-key order', function (): void {
    $areaOne = AddressArea::query()->create([
        'country_code' => 'MY', 'type' => 'locality', 'level' => 1, 'name' => 'Area One',
        'slug' => 'area-one', 'source' => 'areas', 'source_id' => 'one',
    ]);
    $areaTwo = AddressArea::query()->create([
        'country_code' => 'MY', 'type' => 'locality', 'level' => 1, 'name' => 'Area Two',
        'slug' => 'area-two', 'source' => 'areas', 'source_id' => 'two',
    ]);
    $postcode = PostalCode::query()->create(['country_code' => 'MY', 'code' => '50450']);
    $queries = [];
    DB::listen(static function (QueryExecuted $query) use (&$queries): void {
        if (str_starts_with(mb_strtolower($query->sql), 'select')) {
            $queries[] = mb_strtolower($query->sql);
        }
    });

    app(SavePostalCodeAction::class)->execute([
        'manual_coverage' => [
            ['area_id' => $areaTwo->getKey(), 'relationship_type' => 'served_by', 'is_primary' => false],
            ['area_id' => $areaOne->getKey(), 'relationship_type' => 'covers', 'is_primary' => true],
        ],
    ], $postcode);

    $areaTable = mb_strtolower((new AddressArea)->getTable());
    $areaLockQueries = collect($queries)->filter(static fn (string $sql): bool => str_contains($sql, $areaTable)
        && str_contains($sql, 'where "address_areas"."id" in (?, ?)')
        && str_contains($sql, 'order by "id" asc'));

    expect($areaLockQueries)->toHaveCount(1)
        ->and($areaOne->getKey())->not->toBe($areaTwo->getKey());
});
