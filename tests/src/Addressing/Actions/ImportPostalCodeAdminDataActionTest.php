<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\ImportPostalCodeAdminDataAction;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaPostalCode;
use AIArmada\Addressing\Models\PostalCode;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seedCountry('MY');
});

it('imports postcode coverage without conflating root metadata and link provenance', function (): void {
    $areaOne = AddressArea::query()->create([
        'country_code' => 'MY', 'type' => 'locality', 'level' => 1, 'name' => 'Area One',
        'slug' => 'area-one', 'source' => 'areas-one', 'source_id' => 'one',
    ]);
    $areaTwo = AddressArea::query()->create([
        'country_code' => 'MY', 'type' => 'locality', 'level' => 1, 'name' => 'Area Two',
        'slug' => 'area-two', 'source' => 'areas-two', 'source_id' => 'two',
    ]);

    $postcode = app(ImportPostalCodeAdminDataAction::class)->execute(
        countryCode: 'my',
        code: ' 47500 ',
        isActive: false,
        metadata: ['provider' => 'postal-root', 'batch' => 'batch-1'],
        coverage: [
            [
                'source' => 'provider-one',
                'source_id' => 'postcode-one',
                'area_source' => 'areas-one',
                'area_source_id' => 'one',
                'relationship_type' => 'served_by',
                'is_primary' => true,
            ],
            [
                'source' => 'provider-two',
                'source_id' => 'postcode-two',
                'area_source' => 'areas-two',
                'area_source_id' => 'two',
                'relationship_type' => 'covers',
                'is_primary' => false,
            ],
            [
                'source' => 'provider-one',
                'source_id' => 'postcode-one',
                'area_source' => 'areas-two',
                'area_source_id' => 'two',
                'relationship_type' => 'covers',
                'is_primary' => false,
            ],
        ],
    );

    expect($postcode->country_code)->toBe('MY')
        ->and($postcode->code)->toBe('47500')
        ->and($postcode->is_active)->toBeFalse()
        ->and($postcode->metadata)->toBe(['provider' => 'postal-root', 'batch' => 'batch-1'])
        ->and($postcode->areas()->count())->toBe(3)
        ->and(AddressAreaPostalCode::query()->where('postal_code_id', $postcode->getKey())
            ->orderBy('source')->orderBy('relationship_type')->get(['source', 'source_id', 'relationship_type', 'is_primary'])
            ->map(fn (AddressAreaPostalCode $link): array => [
                $link->source,
                $link->source_id,
                $link->relationship_type,
                $link->is_primary,
            ])->all())->toBe([
                ['provider-one', 'postcode-one', 'covers', false],
                ['provider-one', 'postcode-one', 'served_by', true],
                ['provider-two', 'postcode-two', 'covers', false],
            ]);
});

it('replaces only coverage source scopes explicitly present in the imported payload', function (): void {
    $areaOne = AddressArea::query()->create([
        'country_code' => 'MY', 'type' => 'locality', 'level' => 1, 'name' => 'Area One',
        'slug' => 'area-one', 'source' => 'areas', 'source_id' => 'one',
    ]);
    $areaTwo = AddressArea::query()->create([
        'country_code' => 'MY', 'type' => 'locality', 'level' => 1, 'name' => 'Area Two',
        'slug' => 'area-two', 'source' => 'areas', 'source_id' => 'two',
    ]);
    $postcode = PostalCode::query()->create([
        'country_code' => 'MY', 'code' => '47500', 'metadata' => ['preserve' => true],
    ]);
    AddressAreaPostalCode::query()->create([
        'postal_code_id' => $postcode->getKey(), 'address_area_id' => $areaOne->getKey(),
        'source' => 'provider-one', 'source_id' => 'one', 'relationship_type' => 'served_by', 'is_primary' => true,
    ]);
    AddressAreaPostalCode::query()->create([
        'postal_code_id' => $postcode->getKey(), 'address_area_id' => $areaTwo->getKey(),
        'source' => 'provider-two', 'source_id' => 'two', 'relationship_type' => 'covers', 'is_primary' => false,
    ]);

    app(ImportPostalCodeAdminDataAction::class)->execute(
        countryCode: 'MY',
        code: '47500',
        isActive: true,
        metadata: ['preserve' => true],
        coverage: [[
            'source' => 'provider-one',
            'source_id' => 'one',
            'area_source' => 'areas',
            'area_source_id' => 'two',
            'relationship_type' => 'served_by',
            'is_primary' => false,
        ]],
    );

    expect(AddressAreaPostalCode::query()->where('postal_code_id', $postcode->getKey())
        ->orderBy('source')->get(['source', 'address_area_id'])->map(fn (AddressAreaPostalCode $link): array => [
            $link->source,
            $link->address_area_id,
        ])->all())->toBe([
            ['provider-one', $areaTwo->getKey()],
            ['provider-two', $areaTwo->getKey()],
        ]);

    app(ImportPostalCodeAdminDataAction::class)->execute(
        countryCode: 'MY',
        code: '47500',
        isActive: true,
        metadata: ['preserve' => true],
        coverage: [],
    );

    expect(AddressAreaPostalCode::query()->where('postal_code_id', $postcode->getKey())->count())->toBe(2);
});

it('keeps null and empty source identifiers as distinct replacement scopes', function (): void {
    $areaOne = AddressArea::query()->create([
        'country_code' => 'MY', 'type' => 'locality', 'level' => 1, 'name' => 'Area One',
        'slug' => 'area-one', 'source' => 'areas', 'source_id' => 'one',
    ]);
    $areaTwo = AddressArea::query()->create([
        'country_code' => 'MY', 'type' => 'locality', 'level' => 1, 'name' => 'Area Two',
        'slug' => 'area-two', 'source' => 'areas', 'source_id' => 'two',
    ]);
    $postcode = PostalCode::query()->create(['country_code' => 'MY', 'code' => '47500']);
    AddressAreaPostalCode::query()->create([
        'postal_code_id' => $postcode->getKey(), 'address_area_id' => $areaOne->getKey(),
        'source' => 'provider', 'source_id' => null, 'relationship_type' => 'served_by', 'is_primary' => false,
    ]);
    AddressAreaPostalCode::query()->create([
        'postal_code_id' => $postcode->getKey(), 'address_area_id' => $areaTwo->getKey(),
        'source' => 'provider', 'source_id' => '', 'relationship_type' => 'served_by', 'is_primary' => false,
    ]);

    app(ImportPostalCodeAdminDataAction::class)->execute(
        countryCode: 'MY',
        code: '47500',
        isActive: true,
        metadata: null,
        coverage: [
            [
                'source' => 'provider', 'source_id' => null, 'area_source' => 'areas',
                'area_source_id' => 'two', 'relationship_type' => 'served_by', 'is_primary' => true,
            ],
            [
                'source' => 'provider', 'source_id' => '', 'area_source' => 'areas',
                'area_source_id' => 'one', 'relationship_type' => 'served_by', 'is_primary' => false,
            ],
        ],
    );

    expect(AddressAreaPostalCode::query()->where('postal_code_id', $postcode->getKey())
        ->get(['address_area_id', 'source_id', 'is_primary'])
        ->map(fn (AddressAreaPostalCode $link): array => [
            $link->address_area_id,
            $link->source_id,
            $link->is_primary,
        ])->sortBy(static fn (array $link): string => $link[1] === null ? "\0" : "\1" . $link[1])->values()->all())->toBe([
            [$areaTwo->getKey(), null, true],
            [$areaOne->getKey(), '', false],
        ]);
});

it('validates the full imported coverage set before mutating the postcode or its links', function (): void {
    $area = AddressArea::query()->create([
        'country_code' => 'MY', 'type' => 'locality', 'level' => 1, 'name' => 'Area One',
        'slug' => 'area-one', 'source' => 'areas', 'source_id' => 'one',
    ]);

    expect(fn () => app(ImportPostalCodeAdminDataAction::class)->execute(
        countryCode: 'MY',
        code: '47500',
        isActive: true,
        metadata: ['incoming' => true],
        coverage: [[
            'source' => 'provider-one',
            'source_id' => 'one',
            'area_source' => 'areas',
            'area_source_id' => 'one',
            'relationship_type' => 'served_by',
            'is_primary' => true,
        ], [
            'source' => 'provider-two',
            'source_id' => 'two',
            'area_source' => 'areas',
            'area_source_id' => 'missing',
            'relationship_type' => 'served_by',
            'is_primary' => false,
        ]],
    ))->toThrow(ValidationException::class);

    expect(PostalCode::query()->where('code', '47500')->exists())->toBeFalse()
        ->and(AddressAreaPostalCode::query()->where('address_area_id', $area->getKey())->exists())->toBeFalse();
});

it('locks the postcode before ordered area locks for grouped coverage imports', function (): void {
    $areaOne = AddressArea::query()->create([
        'country_code' => 'MY', 'type' => 'locality', 'level' => 1, 'name' => 'Area One',
        'slug' => 'area-one', 'source' => 'areas', 'source_id' => 'one',
    ]);
    $areaTwo = AddressArea::query()->create([
        'country_code' => 'MY', 'type' => 'locality', 'level' => 1, 'name' => 'Area Two',
        'slug' => 'area-two', 'source' => 'areas', 'source_id' => 'two',
    ]);
    $queries = [];
    DB::listen(static function (QueryExecuted $query) use (&$queries): void {
        if (str_starts_with(mb_strtolower($query->sql), 'select')) {
            $queries[] = mb_strtolower($query->sql);
        }
    });

    app(ImportPostalCodeAdminDataAction::class)->execute(
        countryCode: 'MY',
        code: '47500',
        isActive: true,
        metadata: null,
        coverage: [
            [
                'source' => 'provider', 'source_id' => 'feed', 'area_source' => 'areas',
                'area_source_id' => 'two', 'relationship_type' => 'served_by', 'is_primary' => false,
            ],
            [
                'source' => 'provider', 'source_id' => 'feed', 'area_source' => 'areas',
                'area_source_id' => 'one', 'relationship_type' => 'covers', 'is_primary' => true,
            ],
        ],
    );

    $postcodeTable = mb_strtolower((new PostalCode)->getTable());
    $areaTable = mb_strtolower((new AddressArea)->getTable());
    $postcodeLockIndex = collect($queries)->search(static fn (string $sql): bool => str_contains($sql, $postcodeTable)
        && str_contains($sql, 'where "country_code" = ? and "code" = ?'));
    $areaLockIndex = collect($queries)->search(static fn (string $sql): bool => str_contains($sql, $areaTable)
        && str_contains($sql, 'where "country_code" = ?')
        && str_contains($sql, '"source_id" = ?')
        && str_contains($sql, 'order by "id" asc'));

    expect($postcodeLockIndex)->not->toBeFalse()
        ->and($areaLockIndex)->not->toBeFalse()
        ->and($postcodeLockIndex)->toBeLessThan($areaLockIndex)
        ->and(collect($queries)->filter(static fn (string $sql): bool => str_contains($sql, $areaTable)
            && str_contains($sql, 'where "country_code" = ?')
            && str_contains($sql, '"source_id" = ?')))->toHaveCount(1)
        ->and($areaOne->getKey())->not->toBe($areaTwo->getKey());
});
