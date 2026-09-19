<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Addressing;

use AIArmada\Addressing\AddressingServiceProvider;
use AIArmada\Addressing\Models\AddressCountry;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JsonException;
use Orchestra\Testbench\TestCase as Orchestra;
use RuntimeException;

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
}
