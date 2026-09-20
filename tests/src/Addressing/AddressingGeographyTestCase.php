<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Addressing;

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\AddressingServiceProvider;
use AIArmada\Addressing\Contracts\CountryAddressAreaMetadataProvider;
use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Contracts\CountryHierarchyProvider;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaRole;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JsonException;
use Orchestra\Testbench\TestCase as Orchestra;
use RuntimeException;
use Throwable;

/**
 * Minimal boot for geography provider/formatter tests.
 *
 * The monorepo TestCase boots every package plus Filament/Livewire and
 * rebuilds unrelated tables per test (~2.2s overhead each). Geography
 * tests only touch addressing models, so this case boots addressing
 * alone on in-memory SQLite with addressing migrations only.
 */
abstract class AddressingGeographyTestCase extends Orchestra
{
    use RefreshDatabase;

    /** @var array<string, array<string, mixed>> */
    private static array $countryRows = [];

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            DatabaseServiceProvider::class,
            EventServiceProvider::class,
            AddressingServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../../packages/addressing/database/migrations');
    }

    /**
     * Seed a single country row with real attributes from countries.json.
     *
     * Geography tests exercise one country at a time; seeding all 250
     * rows via SeedAddressCountriesAction is pure overhead here.
     *
     * @throws JsonException
     */
    protected function seedCountry(string $iso2): AddressCountry
    {
        $iso2 = mb_strtoupper($iso2);

        if (self::$countryRows === []) {
            $path = __DIR__ . '/../../../packages/addressing/resources/data/countries.json';
            $raw = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($raw)) {
                throw new RuntimeException('Country data file must contain a JSON array.');
            }

            foreach ($raw as $row) {
                if (isset($row['iso2'])) {
                    self::$countryRows[$row['iso2']] = $row;
                }
            }
        }

        $row = self::$countryRows[$iso2] ?? null;

        if ($row === null) {
            throw new RuntimeException("Unknown country code [{$iso2}].");
        }

        return AddressCountry::query()->firstOrCreate(
            ['iso2' => $row['iso2']],
            [
                'name' => $row['name'],
                'phone_code' => $row['phone_code'] ?? null,
                'iso3' => $row['iso3'] ?? null,
                'numeric_code' => $row['numeric_code'] ?? null,
                'native' => $row['native'] ?? null,
                'capital' => $row['capital'] ?? null,
                'region' => $row['region'] ?? null,
                'subregion' => $row['subregion'] ?? null,
                'tld' => $row['tld'] ?? null,
                'latitude' => is_numeric($row['latitude'] ?? null) ? (float) $row['latitude'] : null,
                'longitude' => is_numeric($row['longitude'] ?? null) ? (float) $row['longitude'] : null,
                'emoji' => $row['emoji'] ?? null,
                'emojiU' => $row['emojiU'] ?? null,
                'translations' => $row['translations'] ?? null,
            ]
        );
    }

    /**
     * Round-robin shard of the configured geography providers.
     *
     * Seeding all providers takes ~95s in one process, so the seed contract
     * test is sharded across files for parallel runs.
     *
     * @return list<class-string>
     */
    protected function geographySeedShard(int $shard, int $total = 4): array
    {
        $providers = array_values(array_filter(
            config('addressing.geography.providers', []),
            is_string(...),
        ));

        $shardProviders = [];

        foreach ($providers as $index => $providerClass) {
            if ($index % $total === $shard) {
                $shardProviders[] = $providerClass;
            }
        }

        return $shardProviders;
    }

    /**
     * Seed one provider and check structural consistency.
     *
     * Exact row counts are intentionally not pinned: provider data changes
     * legitimately, and the seeder itself throws on dangling mappings or
     * failed rows. Returns failure messages, empty on success.
     *
     * @param  class-string  $providerClass
     * @return list<string>
     */
    protected function seedProviderConsistently(string $providerClass): array
    {
        $provider = app($providerClass);

        if (! $provider instanceof CountryGeographyProvider || ! $provider instanceof CountryHierarchyProvider) {
            return ["{$providerClass} does not implement the geography provider contracts."];
        }

        $code = $provider->countryCode();

        try {
            $country = $this->seedCountry($code);
            $result = app(SeedCountryGeographiesAction::class)->execute($code);
        } catch (Throwable $exception) {
            return ["{$code}: seeding threw {$exception->getMessage()}."];
        }

        $failures = [];

        if (! in_array($code, $result['seeded'], true)) {
            $failures[] = "{$code}: seeding did not report the country as seeded.";
        }

        $stateCount = State::query()->where('country_id', $country->getKey())->count();
        $areaCount = AddressArea::query()->where('country_id', $country->getKey())->where('is_active', true)->count();
        $linkCount = AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->getKey()),
        )->count();
        $mappingCount = count($provider->stateAreaMappings());

        if ($stateCount === 0) {
            $failures[] = "{$code}: seeding produced no states.";
        }

        if ($areaCount === 0) {
            $failures[] = "{$code}: seeding produced no active areas.";
        }

        if ($linkCount < $mappingCount) {
            $failures[] = "{$code}: {$linkCount} state links for {$mappingCount} mappings.";
        }

        if (! $provider instanceof CountryAddressAreaMetadataProvider) {
            return $failures;
        }

        $shipped = $provider->addressAreaSource()->areas()
            ->map(static fn (AddressAreaData $area): string => $area->sourceId)
            ->flip()
            ->all();

        $declaredRoles = $provider->areaRoles($country);
        $declaredNames = $provider->areaNames($country);
        $declaredRelationships = $provider->areaRelationships($country);

        foreach (array_keys($declaredRoles) as $sourceId) {
            if (! isset($shipped[$sourceId])) {
                $failures[] = "{$code}: role references an unshipped area [{$sourceId}].";
            }
        }

        foreach (array_keys($declaredNames) as $sourceId) {
            if (! isset($shipped[$sourceId])) {
                $failures[] = "{$code}: alias references an unshipped area [{$sourceId}].";
            }
        }

        foreach ($declaredRelationships as $childSourceId => $relationships) {
            if (! isset($shipped[$childSourceId])) {
                $failures[] = "{$code}: relationship references an unshipped child [{$childSourceId}].";
            }

            foreach ($relationships as $relationship) {
                if (! isset($shipped[$relationship['parent_source_id']])) {
                    $failures[] = "{$code}: relationship [{$childSourceId}] references an unshipped parent [{$relationship['parent_source_id']}].";
                }
            }
        }

        $providerKey = $provider->providerKey();
        $countryKey = $country->getKey();

        $countDeclared = static fn (array $declared): int => array_sum(array_map(count(...), $declared));

        // The metadata sync silently skips dangling declarations, so declared
        // rows must equal persisted rows: any gap is dropped data.
        $persistedRoles = AddressAreaRole::query()
            ->where('source', $providerKey)
            ->whereHas('area', fn ($query) => $query->where('country_id', $countryKey))
            ->count();

        if ($persistedRoles !== $countDeclared($declaredRoles)) {
            $failures[] = "{$code}: {$persistedRoles} roles persisted for {$countDeclared($declaredRoles)} declared.";
        }

        $persistedNames = AddressAreaName::query()
            ->where('source', $providerKey)
            ->whereHas('area', fn ($query) => $query->where('country_id', $countryKey))
            ->count();

        if ($persistedNames !== $countDeclared($declaredNames)) {
            $failures[] = "{$code}: {$persistedNames} aliases persisted for {$countDeclared($declaredNames)} declared.";
        }

        $persistedRelationships = AddressAreaRelationship::query()
            ->where('source', $providerKey)
            ->whereHas('child', fn ($query) => $query->where('country_id', $countryKey))
            ->count();

        if ($persistedRelationships !== $countDeclared($declaredRelationships)) {
            $failures[] = "{$code}: {$persistedRelationships} relationships persisted for {$countDeclared($declaredRelationships)} declared.";
        }

        return $failures;
    }
}
