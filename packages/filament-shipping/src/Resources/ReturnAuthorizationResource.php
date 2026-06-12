<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\Pages;
use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\RelationManagers;
use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\Schemas\ReturnAuthorizationForm;
use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\Tables\ReturnAuthorizationsTable;
use AIArmada\Shipping\Models\ReturnAuthorization;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ReturnAuthorizationResource extends Resource
{
    protected static ?string $model = ReturnAuthorization::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-shipping.navigation.group');
    }

    protected static ?string $navigationLabel = 'Returns';

    /**
     * @return Builder<ReturnAuthorization>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<ReturnAuthorization> $query */
        $query = parent::getEloquentQuery()->withoutGlobalScope(OwnerScope::class);

        if (! (bool) config('shipping.features.owner.enabled', false)) {
            return $query;
        }

        $owner = OwnerContext::resolve();
        if ($owner === null) {
            return $query->whereRaw('0 = 1');
        }

        /** @var Builder<ReturnAuthorization> $scoped */
        $scoped = $query->forOwner($owner, includeGlobal: (bool) config('shipping.features.owner.include_global', false));

        return $scoped;
    }

    public static function form(Schema $schema): Schema
    {
        return ReturnAuthorizationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReturnAuthorizationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReturnAuthorizations::route('/'),
            'create' => Pages\CreateReturnAuthorization::route('/create'),
            'view' => Pages\ViewReturnAuthorization::route('/{record}'),
            'edit' => Pages\EditReturnAuthorization::route('/{record}/edit'),
        ];
    }
}
