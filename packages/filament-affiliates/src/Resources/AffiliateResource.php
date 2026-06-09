<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\Pages\CreateAffiliate;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\Pages\EditAffiliate;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\Pages\ListAffiliates;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\Pages\ViewAffiliate;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\RelationManagers\ConversionsRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\RelationManagers\PayoutHoldsRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\RelationManagers\PayoutMethodsRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\RelationManagers\PayoutsRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\RelationManagers\ProgramsRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\RelationManagers\VouchersRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\Schemas\AffiliateForm;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\Schemas\AffiliateInfolist;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\Tables\AffiliatesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class AffiliateResource extends Resource
{
    protected static ?string $model = Affiliate::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Affiliates';

    protected static ?string $modelLabel = 'Affiliate';

    protected static ?string $pluralModelLabel = 'Affiliates';

    public static function canViewAny(): bool
    {
        return FilamentPermission::hasAbility('affiliate.viewAny');
    }

    public static function canView(Model $record): bool
    {
        return FilamentPermission::hasAbility('affiliate.view');
    }

    public static function canCreate(): bool
    {
        return FilamentPermission::hasAbility('affiliate.create');
    }

    public static function canEdit(Model $record): bool
    {
        return FilamentPermission::hasAbility('affiliate.update');
    }

    public static function canDelete(Model $record): bool
    {
        return FilamentPermission::hasAbility('affiliate.delete');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var Builder $query */
        $query = parent::getEloquentQuery();

        return OwnerUiScope::apply($query, includeGlobal: false);
    }

    public static function form(Schema $schema): Schema
    {
        return AffiliateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliatesTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliateInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            ConversionsRelationManager::class,
            ProgramsRelationManager::class,
            PayoutsRelationManager::class,
            PayoutMethodsRelationManager::class,
            PayoutHoldsRelationManager::class,
            VouchersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliates::route('/'),
            'create' => CreateAffiliate::route('/create'),
            'view' => ViewAffiliate::route('/{record}'),
            'edit' => EditAffiliate::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliates.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliates.resources.navigation_sort.affiliates', 60);
    }
}
