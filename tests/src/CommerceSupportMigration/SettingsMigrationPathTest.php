<?php

declare(strict_types=1);

use AIArmada\Affiliates\AffiliatesServiceProvider;
use AIArmada\CommerceSupport\SupportServiceProvider;
use AIArmada\FilamentAffiliates\FilamentAffiliatesServiceProvider;
use AIArmada\FilamentCommerceSupport\FilamentCommerceSupportServiceProvider;
use AIArmada\Pricing\PricingServiceProvider;
use AIArmada\Tax\TaxServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider as FilamentSupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Livewire\LivewireServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Symfony\Component\Process\Process;

function settingsMigrationPathRepositoryRoot(): string
{
    return dirname(__DIR__, 3);
}

/**
 * @return list<class-string>
 */
function settingsMigrationPathProviders(bool $settingsFirst): array
{
    $settings = LaravelSettingsServiceProvider::class;
    $packages = [
        SupportServiceProvider::class,
        AffiliatesServiceProvider::class,
        PricingServiceProvider::class,
        TaxServiceProvider::class,
        FilamentAffiliatesServiceProvider::class,
        FilamentCommerceSupportServiceProvider::class,
    ];

    $ui = [
        LivewireServiceProvider::class,
        FilamentSupportServiceProvider::class,
        ActionsServiceProvider::class,
        SchemasServiceProvider::class,
        FormsServiceProvider::class,
        TablesServiceProvider::class,
        NotificationsServiceProvider::class,
        FilamentServiceProvider::class,
    ];

    return $settingsFirst
        ? [$settings, ...$ui, ...$packages]
        : [...$ui, ...$packages, $settings];
}

/**
 * @param  list<class-string>  $providers
 */
function settingsMigrationPathWriteHarness(string $base, array $providers): void
{
    $root = settingsMigrationPathRepositoryRoot();
    $autoload = $root . '/vendor/autoload.php';
    $autoloadExport = var_export($autoload, true);

    foreach (['bootstrap/cache', 'config', 'routes', 'cache', 'database', 'storage/framework/views', 'storage/framework/cache', 'storage/framework/sessions'] as $directory) {
        $path = $base . '/' . $directory;

        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException('Could not create settings harness directory [' . $path . '].');
        }
    }

    $providerLines = implode("\n", array_map(
        static fn (string $provider): string => '        ' . $provider . '::class,',
        $providers,
    ));

    $bootstrap = <<<PHP
<?php

require {$autoloadExport};

return \\Illuminate\\Foundation\\Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
{$providerLines}
    ], withBootstrapProviders: false)
    ->withRouting(web: __DIR__ . '/../routes/web.php', health: '/up')
    ->withMiddleware(function (): void {})
    ->withExceptions(function (): void {})
    ->create();
PHP;

    file_put_contents($base . '/bootstrap/app.php', $bootstrap);
    file_put_contents($base . '/config/app.php', <<<'PHP'
<?php

return [
    'name' => 'Settings Migration Harness',
    'env' => 'testing',
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
];
PHP);
    file_put_contents($base . '/config/database.php', <<<'PHP'
<?php

return [
    'default' => 'testing',
    'connections' => [
        'testing' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ],
    ],
    'migrations' => 'migrations',
];
PHP);
    file_put_contents($base . '/config/cache.php', <<<'PHP'
<?php

return [
    'default' => 'array',
    'stores' => [
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],
    ],
];
PHP);
    file_put_contents($base . '/routes/controller.php', <<<'PHP'
<?php

final class SettingsMigrationPathHarnessController
{
    public function __invoke(): string
    {
        return 'ok';
    }
}
PHP);
    file_put_contents($base . '/routes/web.php', <<<'PHP'
<?php

require_once __DIR__ . '/controller.php';

\Illuminate\Support\Facades\Route::get('/', SettingsMigrationPathHarnessController::class);
PHP);
    file_put_contents($base . '/artisan', <<<PHP
<?php

require {$autoloadExport};

\$app = require __DIR__ . '/bootstrap/app.php';
\$kernel = \$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class);
\$input = new \\Symfony\\Component\\Console\\Input\\ArgvInput;
\$output = new \\Symfony\\Component\\Console\\Output\\ConsoleOutput;
\$status = \$kernel->handle(\$input, \$output);
\$kernel->terminate(\$input, \$status);

exit(\$status);
PHP);
    file_put_contents($base . '/inspect.php', <<<PHP
<?php

require {$autoloadExport};

\$app = require __DIR__ . '/bootstrap/app.php';
\$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
\$normalize = static fn (string \$path): string => realpath(\$path) ?: \$path;

echo json_encode([
    'paths' => array_values(array_map(\$normalize, \$app->make('migrator')->paths())),
    'settings' => array_values(array_map(\$normalize, \$app->make('config')->get('settings.migrations_paths', []))),
    'config_cached' => \$app->configurationIsCached(),
    'routes_cached' => \$app->routesAreCached(),
], JSON_THROW_ON_ERROR);
PHP);
}

/**
 * @param  array<string, string>  $environment
 */
function settingsMigrationPathRun(string $base, array $arguments, array $environment): Process
{
    $process = new Process([PHP_BINARY, $base . '/artisan', ...$arguments], $base, $environment, null, 120);
    $process->run();

    if (! $process->isSuccessful()) {
        throw new RuntimeException(sprintf(
            'Settings harness command [%s] failed: %s%s',
            implode(' ', $arguments),
            $process->getErrorOutput(),
            $process->getOutput(),
        ));
    }

    return $process;
}

/**
 * @param  array<string, string>  $environment
 * @return array{paths: list<string>, settings: list<string>, config_cached: bool, routes_cached: bool}
 */
function settingsMigrationPathInspect(string $base, array $environment): array
{
    $process = new Process([PHP_BINARY, $base . '/inspect.php'], $base, $environment, null, 120);
    $process->run();

    if (! $process->isSuccessful()) {
        throw new RuntimeException('Settings harness inspection failed: ' . $process->getErrorOutput() . $process->getOutput());
    }

    $decoded = json_decode(mb_trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($decoded)) {
        throw new RuntimeException('Settings harness inspection returned invalid JSON.');
    }

    return $decoded;
}

function settingsMigrationPathRemove(string $base): void
{
    if (! is_dir($base)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($base);
}

/**
 * @return list<string>
 */
function settingsMigrationPathExpectedPaths(): array
{
    $root = settingsMigrationPathRepositoryRoot();

    return array_map(
        static fn (string $package): string => realpath($root . '/packages/' . $package . '/database/settings') ?: $root . '/packages/' . $package . '/database/settings',
        ['commerce-support', 'affiliates', 'pricing', 'tax', 'filament-affiliates', 'filament-commerce-support'],
    );
}

it('exposes package settings migrations to the migrator after config and route caching', function (bool $settingsFirst): void {
    $base = sys_get_temp_dir() . '/aiarmada-settings-path-' . bin2hex(random_bytes(8));
    $cachePath = $base . '/cache/config.php';
    $routesPath = $base . '/cache/routes.php';

    $environment = [
        'APP_KEY' => 'base64:' . base64_encode(str_repeat('a', 32)),
        'APP_CONFIG_CACHE' => $cachePath,
        'APP_ROUTES_CACHE' => $routesPath,
    ];

    try {
        settingsMigrationPathWriteHarness($base, settingsMigrationPathProviders($settingsFirst));

        expect(is_file($base . '/config/settings.php'))->toBeFalse()
            ->and(glob($base . '/database/migrations/*_create_settings_table.php') ?: [])->toBe([]);

        $beforeCache = settingsMigrationPathInspect($base, $environment);
        $expected = settingsMigrationPathExpectedPaths();

        foreach ($expected as $path) {
            expect($beforeCache['settings'])->toContain($path)
                ->and($beforeCache['paths'])->toContain($path);
        }

        settingsMigrationPathRun($base, ['config:cache'], $environment);

        expect(is_file($cachePath))->toBeTrue();

        $afterConfigCache = settingsMigrationPathInspect($base, $environment);

        expect($afterConfigCache['config_cached'])->toBeTrue();

        foreach ($expected as $path) {
            expect($afterConfigCache['settings'])->toContain($path)
                ->and($afterConfigCache['paths'])->toContain($path);
        }

        settingsMigrationPathRun($base, ['route:cache'], $environment);

        $afterRouteCache = settingsMigrationPathInspect($base, $environment);

        expect($afterRouteCache['config_cached'])->toBeTrue()
            ->and($afterRouteCache['routes_cached'])->toBeTrue();

        foreach ($expected as $path) {
            expect($afterRouteCache['settings'])->toContain($path)
                ->and($afterRouteCache['paths'])->toContain($path);
        }
    } finally {
        settingsMigrationPathRemove($base);
    }
})->with([true, false]);
