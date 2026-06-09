<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources;

use AIArmada\FilamentProducts\Resources\CollectionResource\Pages;
use AIArmada\FilamentProducts\Resources\CollectionResource\Schemas\CollectionForm;
use AIArmada\FilamentProducts\Resources\CollectionResource\Schemas\CollectionInfolist;
use AIArmada\FilamentProducts\Resources\CollectionResource\Tables\CollectionsTable;
use AIArmada\Products\Models\Collection;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CollectionResource extends BaseCatalogResource
{
    protected static ?string $model = Collection::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static function navigationSortKey(): string
    {
        return 'collections';
    }

    /**
     * @return Builder<Collection>
     */
    public static function getEloquentQuery(): Builder
    {
        return Collection::query()
            ->forOwner()
            ->withCount(['products']);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('is_visible', true)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return CollectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CollectionsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CollectionInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCollections::route('/'),
            'create' => Pages\CreateCollection::route('/create'),
            'view' => Pages\ViewCollection::route('/{record}'),
            'edit' => Pages\EditCollection::route('/{record}/edit'),
        ];
    }
}
