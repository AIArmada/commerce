<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\AddressAreaBoundaryWriter;
use AIArmada\Addressing\Features\AddressLifecycle\Models\Address;
use AIArmada\Addressing\Features\Geography\Models\AddressArea;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('leaves spatial columns and queries disabled by default', function (): void {
    expect(Schema::hasColumn('addresses', 'location'))->toBeFalse()
        ->and(Schema::hasColumn('addresses', 'geohash'))->toBeFalse()
        ->and(Schema::hasColumn('addresses', 'h3_cell'))->toBeFalse()
        ->and(Schema::hasColumn('address_areas', 'boundary'))->toBeFalse()
        ->and(fn () => Address::query()->withinRadius(3.139, 101.6869, 500))->toThrow(LogicException::class);
});

it('enables indexed spatial queries on existing rows and preserves address owner scope', function (): void {
    $schema = 'addressing_' . Str::lower(Str::random(16));
    $originalConnection = DB::getDefaultConnection();
    config()->set('database.connections.addressing_spatial', [
        'driver' => 'pgsql',
        'url' => getenv('ADDRESSING_SPATIAL_TEST_DATABASE_URL'),
        'search_path' => "{$schema},public",
        'prefix' => 'spatial_',
    ]);
    $connection = DB::connection('addressing_spatial');
    $connection->statement('CREATE SCHEMA "' . $schema . '"');

    try {
        DB::setDefaultConnection('addressing_spatial');
        config()->set('addressing.database.tables.addresses', 'custom_addresses');
        config()->set('addressing.database.tables.areas', 'custom_areas');
        $options = [
            '--path' => realpath(__DIR__ . '/../../../../packages/addressing/database/migrations'),
            '--realpath' => true,
            '--force' => true,
        ];

        expect(Artisan::call('migrate', $options))->toBe(0);
        expect(Schema::hasColumn('custom_addresses', 'location'))->toBeFalse();

        $ownerA = new User(['name' => 'Owner A']);
        $ownerA->id = (string) Str::uuid();
        $ownerB = new User(['name' => 'Owner B']);
        $ownerB->id = (string) Str::uuid();

        $coordinates = ['latitude' => 48, 'longitude' => -126];
        $address = OwnerContext::withOwner($ownerA, fn () => Address::query()->create($coordinates));
        OwnerContext::withOwner($ownerB, fn () => Address::query()->create($coordinates));
        $area = AddressArea::query()->create([
            'name' => 'Test area',
            'slug' => 'test-area',
            'country_code' => 'CA',
            'type' => 'region',
            'source' => 'test',
            'source_id' => 'test-area',
            ...$coordinates,
        ]);

        config()->set('addressing.features.spatial.geohash', true);
        expect(Artisan::call('migrate', $options))->toBe(0);
        expect(Schema::hasColumn('custom_addresses', 'location'))->toBeFalse()
            ->and($area->refresh()->getAttribute('geohash'))->toBe('c0w3hf1s70w3');

        config()->set('addressing.features.spatial.postgis', true);
        config()->set('addressing.features.spatial.h3', true);
        expect(Artisan::call('migrate', $options))->toBe(0);
        expect(Schema::hasColumn('custom_areas', 'boundary'))->toBeTrue()
            ->and($area->refresh()->getAttribute('h3_cell'))->toBe('8928c3badd3ffff');

        OwnerContext::withOwner($ownerA, function () use ($address): void {
            expect(Address::query()->withinRadius(48, -126, 500)->sole()->id)->toBe($address->id)
                ->and(Address::query()->inGeohash('c0w3h')->sole()->id)->toBe($address->id)
                ->and(Address::query()->inH3Cell(48, -126)->sole()->id)->toBe($address->id);

            $address->update(['longitude' => null]);

            expect(Address::query()->withinRadius(48, -126, 500)->exists())->toBeFalse()
                ->and(Address::query()->inGeohash('c0w3h')->exists())->toBeFalse()
                ->and(Address::query()->inH3Cell(48, -126)->exists())->toBeFalse();
        });

        $boundaries = app(AddressAreaBoundaryWriter::class);
        $boundaries->execute($area->id, 'POLYGON((-126 48,-125 48,-125 49,-126 49,-126 48))');
        expect(AddressArea::query()->coveringPoint(48, -126)->sole()->id)->toBe($area->id)
            ->and(AddressArea::query()->coveringPoint(47, -126)->exists())->toBeFalse()
            ->and(fn () => $boundaries->execute($area->id, 'POINT(-126 48)'))->toThrow(InvalidArgumentException::class);

        $boundaries->execute($area->id, null);
        expect(AddressArea::query()->coveringPoint(48, -126)->exists())->toBeFalse();
    } finally {
        DB::setDefaultConnection($originalConnection);
        $connection->statement('DROP SCHEMA "' . $schema . '" CASCADE');
        DB::purge('addressing_spatial');
    }
})->skip(! getenv('ADDRESSING_SPATIAL_TEST_DATABASE_URL'), 'Set ADDRESSING_SPATIAL_TEST_DATABASE_URL to a test database with PostGIS and h3 installed.');
