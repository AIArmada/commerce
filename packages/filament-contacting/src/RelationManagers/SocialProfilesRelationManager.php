<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\RelationManagers;

use AIArmada\FilamentContacting\Schemas\SocialProfileFormSchema;
use AIArmada\FilamentContacting\Support\GuardsContactingUi;
use AIArmada\FilamentContacting\Tables\SocialProfileTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Table;

final class SocialProfilesRelationManager extends RelationManager
{
    protected static string $relationship = 'socialProfiles';

    protected static ?string $title = 'Social Profiles';

    protected static ?string $recordTitleAttribute = 'handle';

    public function table(Table $table): Table
    {
        $guard = app(GuardsContactingUi::class);

        $tableConfig = SocialProfileTable::table($table);

        $tableConfig
            ->headerActions([
                CreateAction::make()
                    ->form(SocialProfileFormSchema::make())
                    ->visible(fn (): bool => ! $guard->socialProfilesReadOnly()),
            ]);

        return $tableConfig;
    }
}
