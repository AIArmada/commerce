<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Features\Geography;

use AIArmada\Addressing\Contracts\AddressAreaAliasDeleter;
use AIArmada\Addressing\Contracts\AddressAreaAliasWriter;
use AIArmada\Addressing\Contracts\AddressAreaBoundaryWriter;
use AIArmada\Addressing\Contracts\AddressAreaDeletionGuard;
use AIArmada\Addressing\Contracts\AddressAreaImporter;
use AIArmada\Addressing\Contracts\AddressAreaSearch;
use AIArmada\Addressing\Contracts\AddressAreaWriter;
use AIArmada\Addressing\Contracts\AddressCountryDeletionGuard;
use AIArmada\Addressing\Contracts\AddressingDatasetSeeder;
use AIArmada\Addressing\Contracts\CityDatasetSeeder;
use AIArmada\Addressing\Contracts\CountryDatasetSeeder;
use AIArmada\Addressing\Contracts\CountryGeographySeeder;
use AIArmada\Addressing\Contracts\CountryLinkSeeder;
use AIArmada\Addressing\Contracts\CountryStateSeeder;
use AIArmada\Addressing\Contracts\GeographyReferenceEditor;
use AIArmada\Addressing\Contracts\StateDatasetSeeder;
use AIArmada\Addressing\Features\Geography\Actions\ClearGeographyReferencesAction;
use AIArmada\Addressing\Features\Geography\Actions\DeleteAddressAreaAliasAction;
use AIArmada\Addressing\Features\Geography\Actions\EditGeographyReferenceAction;
use AIArmada\Addressing\Features\Geography\Actions\GuardAddressAreaDeletionAction;
use AIArmada\Addressing\Features\Geography\Actions\GuardAddressCountryDeletionAction;
use AIArmada\Addressing\Features\Geography\Actions\ImportAddressAreasAction;
use AIArmada\Addressing\Features\Geography\Actions\PruneDuplicateManualAliasesAction;
use AIArmada\Addressing\Features\Geography\Actions\SaveAddressAreaAction;
use AIArmada\Addressing\Features\Geography\Actions\SaveAddressAreaAliasAction;
use AIArmada\Addressing\Features\Geography\Actions\SearchAddressAreasAction;
use AIArmada\Addressing\Features\Geography\Actions\SeedAddressCitiesAction;
use AIArmada\Addressing\Features\Geography\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Features\Geography\Actions\SeedAddressCountryReferencesAction;
use AIArmada\Addressing\Features\Geography\Actions\SeedAddressingAction;
use AIArmada\Addressing\Features\Geography\Actions\SeedAddressStatesAction;
use AIArmada\Addressing\Features\Geography\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Features\Geography\Actions\SeedCountryStatesAction;
use AIArmada\Addressing\Features\Geography\Actions\SetAddressAreaBoundaryAction;
use AIArmada\Addressing\Features\Geography\Api\GeographyReferenceMaintenance;
use AIArmada\Addressing\Features\Geography\Api\ResolutionAliasMaintenance;
use AIArmada\Addressing\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->singleton(GeographyReferenceMaintenance::class, ClearGeographyReferencesAction::class);
        $app->singleton(ResolutionAliasMaintenance::class, PruneDuplicateManualAliasesAction::class);
        $app->singleton(GeographyReferenceEditor::class, EditGeographyReferenceAction::class);
        $app->singleton(CityDatasetSeeder::class, SeedAddressCitiesAction::class);
        $app->singleton(CountryDatasetSeeder::class, SeedAddressCountriesAction::class);
        $app->singleton(CountryLinkSeeder::class, SeedAddressCountryReferencesAction::class);
        $app->singleton(StateDatasetSeeder::class, SeedAddressStatesAction::class);
        $app->singleton(AddressingDatasetSeeder::class, SeedAddressingAction::class);
        $app->singleton(AddressAreaImporter::class, ImportAddressAreasAction::class);
        $app->singleton(CountryGeographySeeder::class, SeedCountryGeographiesAction::class);
        $app->singleton(AddressAreaDeletionGuard::class, GuardAddressAreaDeletionAction::class);
        $app->singleton(AddressCountryDeletionGuard::class, GuardAddressCountryDeletionAction::class);
        $app->scoped(AddressAreaSearch::class, SearchAddressAreasAction::class);
        $app->singleton(CountryStateSeeder::class, SeedCountryStatesAction::class);
        $app->singleton(AddressAreaWriter::class, SaveAddressAreaAction::class);
        $app->singleton(AddressAreaAliasWriter::class, SaveAddressAreaAliasAction::class);
        $app->singleton(AddressAreaAliasDeleter::class, DeleteAddressAreaAliasAction::class);
        $app->singleton(AddressAreaBoundaryWriter::class, SetAddressAreaBoundaryAction::class);
    }

    public function boot(Application $app): void {}
}
