<?php

declare(strict_types=1);

namespace AIArmada\FilamentLinks\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\FilamentLinks\Resources\LinkResource\Pages;
use AIArmada\FilamentLinks\Resources\LinkResource\RelationManagers\ClicksRelationManager;
use AIArmada\FilamentLinks\Resources\LinkResource\Schemas\LinkForm;
use AIArmada\FilamentLinks\Resources\LinkResource\Tables\LinksTable;
use AIArmada\Links\Models\Link;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class LinkResource extends Resource
{
    protected static ?string $model = Link::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-link';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<Link>
     */
    public static function getEloquentQuery(): Builder
    {
        return OwnerUiScope::apply(Link::query());
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-links.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-links.resources.navigation_sort.links', 10);
    }

    public static function form(Schema $schema): Schema
    {
        return LinkForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LinksTable::configure($table);
    }

    /**
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [
            ClicksRelationManager::class,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLinks::route('/'),
            'create' => Pages\CreateLink::route('/create'),
            'edit' => Pages\EditLink::route('/{record}/edit'),
        ];
    }
}
