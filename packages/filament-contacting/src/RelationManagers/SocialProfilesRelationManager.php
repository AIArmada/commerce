<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\RelationManagers;

use AIArmada\FilamentContacting\Schemas\SocialProfileFormSchema;
use AIArmada\FilamentContacting\Support\ContactingRelationOwnerScope;
use AIArmada\FilamentContacting\Support\GuardsContactingUi;
use AIArmada\FilamentContacting\Tables\SocialProfileTable;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
            ->modifyQueryUsing(fn (Builder $query): Builder => ContactingRelationOwnerScope::scopeForParent($query, $this->getOwnerRecord()))
            ->headerActions([
                CreateAction::make()
                    ->form(SocialProfileFormSchema::make())
                    ->visible(fn (): bool => ! $guard->socialProfilesReadOnly())
                    ->disabled(fn (): bool => $guard->socialProfilesReadOnly())
                    ->before(function (): void {
                        abort_unless(ContactingRelationOwnerScope::canAccessParent($this->getOwnerRecord()), 403);
                    }),
            ]);

        return $tableConfig;
    }
}
