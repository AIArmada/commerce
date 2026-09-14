<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Resources\SocialProfileResource\Pages;

use AIArmada\FilamentContacting\Imports\SocialProfileImporter;
use AIArmada\FilamentContacting\Resources\SocialProfileResource;
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
                ->label('Import Social Profiles'),
        ];
    }
}
