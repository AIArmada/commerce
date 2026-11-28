<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources;

use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\Pages;
use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\RelationManagers;
use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\Schemas\ReturnAuthorizationForm;
use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\Tables\ReturnAuthorizationsTable;
use AIArmada\FilamentShipping\Support\OwnerScopedQuery;
use AIArmada\Shipping\Features\Returns\Models\ReturnAuthorization;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ReturnAuthorizationResource extends Resource
{
    protected static ?string $model = ReturnAuthorization::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-shipping.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-shipping.resources.navigation_sort.returns');
    }

    protected static ?string $navigationLabel = 'Returns';

    /**
     * @return Builder<ReturnAuthorization>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<ReturnAuthorization> $query */
        $query = parent::getEloquentQuery();

        return OwnerScopedQuery::apply($query);
    }

    public static function canEdit(Model $record): bool
    {
        return parent::canEdit($record)
            && (! $record instanceof ReturnAuthorization || ! $record->isPackageReturn());
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
