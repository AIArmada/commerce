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
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class LinkResource extends Resource
{
    protected static ?string $model = Link::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-link';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return self::hasStaffAccess();
    }

    public static function canView(Model $record): bool
    {
        return self::hasStaffAccess();
    }

    public static function canCreate(): bool
    {
        return self::hasStaffAccess();
    }

    public static function canEdit(Model $record): bool
    {
        return self::hasStaffAccess();
    }

    public static function canDelete(Model $record): bool
    {
        return self::hasStaffAccess()
            && $record instanceof Link
            && ! LinkForm::isProtectedSubject($record);
    }

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

    private static function hasStaffAccess(): bool
    {
        $staffRoles = array_values(array_filter((array) config('filament-links.staff_roles', []), 'is_string'));

        if ($staffRoles === []) {
            return true;
        }

        $user = auth()->user();

        foreach ($staffRoles as $role) {
            if (is_object($user) && method_exists($user, 'hasRole') && $user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }
}
