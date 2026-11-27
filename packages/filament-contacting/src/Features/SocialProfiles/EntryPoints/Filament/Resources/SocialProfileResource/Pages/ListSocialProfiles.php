<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Features\SocialProfiles\EntryPoints\Filament\Resources\SocialProfileResource\Pages;

use AIArmada\FilamentContacting\Features\SocialProfiles\EntryPoints\Filament\Imports\SocialProfileImporter;
use AIArmada\FilamentContacting\Features\SocialProfiles\EntryPoints\Filament\Resources\SocialProfileResource;
use AIArmada\FilamentContacting\Support\ContactingFilamentConfig;
use AIArmada\FilamentContacting\Support\GuardsContactingUi;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

final class ListSocialProfiles extends ListRecords
{
    protected static string $resource = SocialProfileResource::class;

    /**
     * @return array<int, ImportAction>
     */
    protected function getHeaderActions(): array
    {
        if (! app(ContactingFilamentConfig::class)->importsEnabled()
            || app(GuardsContactingUi::class)->socialProfilesReadOnly()) {
            return [];
        }

        return [
            ImportAction::make()
                ->importer(SocialProfileImporter::class)
                ->label('Import Social Profiles')
                ->visible(fn (): bool => SocialProfileResource::canCreate())
                ->authorize(fn (): bool => SocialProfileResource::canCreate()),
        ];
    }
}
