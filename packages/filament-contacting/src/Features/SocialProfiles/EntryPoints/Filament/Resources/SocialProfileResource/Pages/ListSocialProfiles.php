<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Features\SocialProfiles\EntryPoints\Filament\Resources\SocialProfileResource\Pages;

use AIArmada\Contacting\Features\SocialProfiles\Models\SocialProfile;
use AIArmada\FilamentContacting\Features\SocialProfiles\EntryPoints\Filament\Imports\SocialProfileImporter;
use AIArmada\FilamentContacting\Features\SocialProfiles\EntryPoints\Filament\Resources\SocialProfileResource;
use AIArmada\FilamentContacting\Support\ContactingFilamentConfig;
use AIArmada\FilamentContacting\Support\GuardsContactingUi;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;

final class ListSocialProfiles extends ListRecords
{
    protected static string $resource = SocialProfileResource::class;

    /**
     * @return array<int, ImportAction>
     */
    protected function getHeaderActions(): array
    {
        if (! self::canImportSocialProfiles()) {
            return [];
        }

        return [
            ImportAction::make()
                ->importer(SocialProfileImporter::class)
                ->label('Import Social Profiles')
                ->visible(static fn (): bool => self::canImportSocialProfiles())
                ->authorize(static fn (): bool => self::canAuthorizeSocialProfileImport()),
        ];
    }

    private static function canImportSocialProfiles(): bool
    {
        return app(ContactingFilamentConfig::class)->importsEnabled()
            && ! app(GuardsContactingUi::class)->socialProfilesReadOnly();
    }

    private static function canAuthorizeSocialProfileImport(): bool
    {
        if (! self::canImportSocialProfiles()) {
            return false;
        }

        return Gate::getPolicyFor(SocialProfile::class) === null
            || Gate::allows('create', SocialProfile::class);
    }
}
