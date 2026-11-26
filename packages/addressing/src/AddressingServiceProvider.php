<?php

declare(strict_types=1);

namespace AIArmada\Addressing;

use AIArmada\Addressing\Actions\GuardAddressAreaDeletionAction;
use AIArmada\Addressing\Actions\GuardAddressCountryDeletionAction;
use AIArmada\Addressing\Actions\ImportAddressAreasAction;
use AIArmada\Addressing\Actions\SearchAddressAreasAction;
use AIArmada\Addressing\Actions\SeedAddressCitiesAction;
use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedAddressCountryReferencesAction;
use AIArmada\Addressing\Actions\SeedAddressingAction;
use AIArmada\Addressing\Actions\SeedAddressStatesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Features\ReferenceIntegrity\EntryPoints\Commands\CompareAddressingReferenceExportsCommand;
use AIArmada\Addressing\Features\ReferenceIntegrity\EntryPoints\Commands\DiffCldrSubdivisionsCommand;
use AIArmada\Addressing\Features\ReferenceIntegrity\EntryPoints\Commands\DiffGoogleAddressReferenceCommand;
use AIArmada\Addressing\Features\ReferenceIntegrity\EntryPoints\Commands\ExportAddressingReferencesCommand;
use AIArmada\Addressing\Features\ResolutionGaps\EntryPoints\Commands\ExportResolutionGapAliasesCommand;
use AIArmada\Addressing\Commands\ImportAddressAreasCommand;
use AIArmada\Addressing\Features\ResolutionGaps\EntryPoints\Commands\ReportResolutionGapsCommand;
use AIArmada\Addressing\Commands\SeedCountryGeographiesCommand;
use AIArmada\Addressing\Features\PostalCodes\EntryPoints\Commands\SeedPostalCodesCommand;
use AIArmada\Addressing\Contracts\GoogleMapsLookup;
use AIArmada\Addressing\Contracts\PostalAddressLookup;
use AIArmada\Addressing\Contracts\CountryAddressFormatter;
use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Drivers\GooglePlaces\GoogleMapsRemoteLookup;
use AIArmada\Addressing\GooglePlaces\GoogleMapsUrlParser;
use AIArmada\Addressing\GooglePlaces\GooglePlaceGeographyMatcher;
use AIArmada\Addressing\GooglePlaces\GooglePlaceGeographyResolver;
use AIArmada\Addressing\GooglePlaces\GooglePlaceResolutionGapLogger;
use AIArmada\Addressing\Support\CountryAddressFormatterResolver;
use AIArmada\Addressing\Support\CountryAddressProfileResolver;
use AIArmada\Addressing\Drivers\OneMap\OneMapClient;
use AIArmada\Addressing\Platform\ExtensionRegistry;
use AIArmada\Addressing\Platform\FeatureCatalog;
use AIArmada\Addressing\Platform\FeatureModule;
use InvalidArgumentException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class AddressingServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('addressing')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations()
            ->hasCommands(
                SeedCountryGeographiesCommand::class,
                SeedPostalCodesCommand::class,
                ImportAddressAreasCommand::class,
                ReportResolutionGapsCommand::class,
                ExportResolutionGapAliasesCommand::class,
                DiffGoogleAddressReferenceCommand::class,
                DiffCldrSubdivisionsCommand::class,
                ExportAddressingReferencesCommand::class,
                CompareAddressingReferenceExportsCommand::class,
            );
    }

    /** @var list<FeatureModule> */
    private array $modules = [];

    public function packageRegistered(): void
    {
        $catalog = FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__);
        $this->app->singleton(ExtensionRegistry::class, fn ($app): ExtensionRegistry => new ExtensionRegistry($app, $catalog['extensions']));

        foreach (array_keys($catalog['features']) as $feature) {
            $module = $this->app->make(__NAMESPACE__ . '\\Features\\' . $feature . '\\FeatureModule');

            if (! $module instanceof FeatureModule) {
                throw new InvalidArgumentException('Invalid addressing feature module: ' . $feature);
            }

            $module->register($this->app);
            $this->modules[] = $module;
        }

        $this->app->singleton(SeedAddressingAction::class);
        $this->app->singleton(SeedAddressCountriesAction::class);
        $this->app->singleton(SeedAddressCountryReferencesAction::class);
        $this->app->singleton(SeedAddressStatesAction::class);
        $this->app->singleton(SeedAddressCitiesAction::class);
        $this->app->singleton(SeedCountryGeographiesAction::class);
        $this->app->singleton(SearchAddressAreasAction::class);
        $this->app->singleton(CountryAddressProfileResolver::class);
        $this->app->singleton(CountryAddressFormatterResolver::class);
        $this->app->singleton(ImportAddressAreasAction::class);
        $this->app->singleton(PostalAddressLookup::class, OneMapClient::class);
        $this->app->singleton(GuardAddressAreaDeletionAction::class);
        $this->app->singleton(GuardAddressCountryDeletionAction::class);
        $this->app->singleton(GoogleMapsUrlParser::class);
        $this->app->singleton(GoogleMapsLookup::class, GoogleMapsRemoteLookup::class);
        $this->app->singleton(GooglePlaceGeographyMatcher::class);
        $this->app->singleton(GooglePlaceResolutionGapLogger::class);
        $this->app->singleton(GooglePlaceGeographyResolver::class);

    }

    public function bootingPackage(): void
    {
        $this->normalizeDefaultCountryCode();
        $this->validateConfiguredClasses(
            config('addressing.geography.providers', []),
            CountryGeographyProvider::class,
            'geography provider',
        );
        $this->validateConfiguredClasses(
            config('addressing.formatters', []),
            CountryAddressFormatter::class,
            'address formatter',
        );

        foreach (config('addressing.area_sources', []) as $source) {
            if (is_string($source)) {
                $this->app->singleton($source);
            }
        }
    }

    public function packageBooted(): void
    {
        foreach ($this->modules as $module) {
            $module->boot($this->app);
        }
    }

    private function normalizeDefaultCountryCode(): void
    {
        $countryCode = config('addressing.defaults.country_code');

        if ($countryCode === null) {
            return;
        }

        if (! is_string($countryCode) || mb_trim($countryCode) === '') {
            config(['addressing.defaults.country_code' => null]);

            return;
        }

        config(['addressing.defaults.country_code' => mb_strtoupper(mb_trim($countryCode))]);
    }

    private function validateConfiguredClasses(mixed $classes, string $contract, string $label): void
    {
        if (! is_array($classes)) {
            throw new InvalidArgumentException(sprintf(
                'Addressing %s configuration must be an array of class strings.',
                $label,
            ));
        }

        foreach ($classes as $class) {
            if (! is_string($class) || ! class_exists($class) || ! is_a($class, $contract, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Addressing %s [%s] must implement [%s].',
                    $label,
                    is_string($class) ? $class : get_debug_type($class),
                    $contract,
                ));
            }
        }
    }
}
