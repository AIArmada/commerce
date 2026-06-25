<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Contacting\Models\SocialProfile;
use AIArmada\FilamentContacting\Schemas\SocialProfileFormSchema;
use AIArmada\FilamentContacting\Schemas\SocialProfileInfolistSchema;
use AIArmada\FilamentContacting\Support\GuardsContactingUi;
use AIArmada\FilamentContacting\Tables\SocialProfileTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class SocialProfileResource extends Resource
{
    protected static ?string $model = SocialProfile::class;

    protected static BackedEnum | string | null $navigationIcon = 'heroicon-o-share';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-contacting.navigation.group');
    }

    public static function getNavigationIcon(): BackedEnum | string | null
    {
        return config('filament-contacting.navigation.icons.social_profiles', parent::getNavigationIcon());
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-contacting.navigation.sort', 70) + 1;
    }

    public static function getEloquentQuery(): Builder
    {
        return OwnerUiScope::apply(parent::getEloquentQuery(), includeGlobal: false);
    }

    public static function table(Table $table): Table
    {
        return SocialProfileTable::table($table);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema(SocialProfileFormSchema::make());
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema(SocialProfileInfolistSchema::make());
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        $guard = app(GuardsContactingUi::class);
        $pages = [
            'index' => SocialProfileResource\Pages\ListSocialProfiles::route('/'),
            'view' => SocialProfileResource\Pages\ViewSocialProfile::route('/{record}'),
        ];

        if (! $guard->socialProfilesReadOnly()) {
            $pages['create'] = SocialProfileResource\Pages\CreateSocialProfile::route('/create');
            $pages['edit'] = SocialProfileResource\Pages\EditSocialProfile::route('/{record}/edit');
        }

        return $pages;
    }
}
