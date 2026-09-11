<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\SupportServiceProvider;
use AIArmada\Events\Models\Event;
use AIArmada\Pricing\PricingServiceProvider;
use AIArmada\Ticketing\Enums\PricingMode;
use AIArmada\Ticketing\Models\TicketType;
use AIArmada\Ticketing\Support\PricingModeResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;

class SettingsBaseTableMigrationTestCase extends OrchestraTestCase
{
    private static string $databasePath;

    private static string $databaseDirectory;

    public static function setUpBeforeClass(): void
    {
        $databaseDirectory = sys_get_temp_dir() . '/aiarmada-settings-' . bin2hex(random_bytes(16));

        if (! mkdir($databaseDirectory . '/migrations', 0755, true) && ! is_dir($databaseDirectory . '/migrations')) {
            throw new RuntimeException('Unable to create a temporary database migrations directory.');
        }

        if (! mkdir($databaseDirectory . '/settings', 0755, true) && ! is_dir($databaseDirectory . '/settings')) {
            throw new RuntimeException('Unable to create a temporary settings migrations directory.');
        }

        $databasePath = tempnam(sys_get_temp_dir(), 'aiarmada-settings-');

        if ($databasePath === false) {
            throw new RuntimeException('Unable to create a temporary SQLite database.');
        }

        self::$databasePath = $databasePath;
        self::$databaseDirectory = $databaseDirectory;

        parent::setUpBeforeClass();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        if (is_file(self::$databasePath)) {
            unlink(self::$databasePath);
        }

        rmdir(self::$databaseDirectory . '/migrations');
        rmdir(self::$databaseDirectory . '/settings');
        rmdir(self::$databaseDirectory);
    }

    protected function setUp(): void
    {
        if (is_file(self::$databasePath)) {
            unlink(self::$databasePath);
        }

        touch(self::$databasePath);

        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelSettingsServiceProvider::class,
            SupportServiceProvider::class,
            PricingServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app->useDatabasePath(self::$databaseDirectory);
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('app.env', 'testing');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => self::$databasePath,
            'prefix' => '',
        ]);
        $app['config']->set('cache.default', 'array');
    }
}

uses(SettingsBaseTableMigrationTestCase::class);

it('creates and re-creates settings before pricing settings migrations', function (): void {
    expect(glob(database_path('migrations/*_create_settings_table.php')) ?: [])->toBe([]);

    $this->artisan('migrate:fresh')->assertExitCode(0);

    expect(Schema::hasTable('settings'))->toBeTrue()
        ->and(settingsMigrationIsRegistered())->toBeTrue();

    expectSettingsToMatchDefaults();

    $this->artisan('migrate')->assertExitCode(0);
    expectSettingsToMatchDefaults();

    $this->refreshApplication();
    $this->artisan('migrate:fresh')->assertExitCode(0);
    expectSettingsToMatchDefaults();
});

it('skips the package settings migration when the application published one exists', function (): void {
    $publishedMigrationPath = database_path('migrations/2022_12_14_083707_create_settings_table.php');

    if (! is_dir(dirname($publishedMigrationPath))) {
        mkdir(dirname($publishedMigrationPath), 0755, true);
    }

    file_put_contents($publishedMigrationPath, <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group');
            $table->string('name');
            $table->boolean('locked')->default(false);
            $table->json('payload');
            $table->timestamps();
            $table->unique(['group', 'name']);
        });
    }
};
PHP);

    try {
        $this->refreshApplication();

        expect(settingsMigrationIsRegistered())->toBeFalse();

        $this->artisan('migrate:fresh')->assertExitCode(0);
        expectSettingsToMatchDefaults();
    } finally {
        if (is_file($publishedMigrationPath)) {
            unlink($publishedMigrationPath);
        }
    }
});

it('drops and restores the settings table during a migration rollback round trip', function (): void {
    $this->artisan('migrate:fresh')->assertExitCode(0);
    expectSettingsToMatchDefaults();

    $this->artisan('migrate:rollback', [
        '--path' => [settingsBaseMigrationPath(), pricingSettingsMigrationPath()],
        '--realpath' => true,
    ])->assertExitCode(0);

    expect(Schema::hasTable('settings'))->toBeFalse();

    $this->artisan('migrate')->assertExitCode(0);
    expectSettingsToMatchDefaults();
});

it('resolves free, paid, and mixed ticket and event pricing modes', function (): void {
    $freeTicket = TicketType::factory()->make(['price' => null]);
    $paidTicket = TicketType::factory()->make(['price' => 1500]);

    expect($freeTicket->effectivePricingMode())->toBe(PricingMode::Free)
        ->and($paidTicket->effectivePricingMode())->toBe(PricingMode::Paid)
        ->and(PricingModeResolver::resolve(new EloquentCollection([$freeTicket, $paidTicket])))
        ->toBe(PricingMode::Mixed);

    $eventModes = [
        [null, PricingMode::Free],
        [0, PricingMode::Free],
        [1500, PricingMode::Paid],
        [0, 1500, PricingMode::Mixed],
    ];

    foreach ($eventModes as $prices) {
        $expected = array_pop($prices);
        $event = Event::factory()->make(['pricing_mode' => null]);
        $event->setRelation(
            'ticketTypes',
            new EloquentCollection(array_map(
                static fn (?int $price): TicketType => TicketType::factory()->make(['price' => $price]),
                $prices,
            )),
        );

        expect($event->effectivePricingMode())->toBe($expected);
    }
});

function settingsBaseMigrationPath(): string
{
    return realpath(__DIR__ . '/../../../packages/commerce-support/database/migrations/1970_01_01_000000_create_settings_table.php') ?: '';
}

function settingsMigrationIsRegistered(): bool
{
    return collect(app('migrator')->paths())
        ->contains(static fn (string $path): bool => str_ends_with($path, basename(settingsBaseMigrationPath())));
}

function pricingSettingsMigrationPath(): string
{
    return realpath(__DIR__ . '/../../../packages/pricing/database/settings') ?: '';
}

function expectSettingsToMatchDefaults(): void
{
    $expected = [
        'pricing.defaultCurrency' => 'MYR',
        'pricing.decimalPlaces' => 2,
        'pricing.pricesIncludeTax' => false,
        'pricing.roundingMode' => 'half_up',
        'pricing.minimumOrderValue' => 0,
        'pricing.maximumOrderValue' => 100000_00,
        'pricing.promotionalPricingEnabled' => true,
        'pricing.tieredPricingEnabled' => true,
        'pricing.customerGroupPricingEnabled' => false,
        'pricing_promotional.flashSalesEnabled' => true,
        'pricing_promotional.defaultFlashSaleDurationHours' => 24,
        'pricing_promotional.maxDiscountPercentage' => 90,
        'pricing_promotional.allowPromotionStacking' => false,
        'pricing_promotional.maxStackablePromotions' => 2,
        'pricing_promotional.showOriginalPrice' => true,
        'pricing_promotional.showCountdownTimers' => true,
    ];

    $rows = DB::table('settings')
        ->orderBy('group')
        ->orderBy('name')
        ->get(['group', 'name', 'payload']);

    expect($rows)->toHaveCount(count($expected));

    $actual = $rows
        ->mapWithKeys(static fn (object $setting): array => [
            "{$setting->group}.{$setting->name}" => json_decode($setting->payload, true),
        ])
        ->all();

    expect($actual)->toEqual($expected);
}
