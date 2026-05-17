<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\States\DocStatus;
use AIArmada\Docs\States\Overdue;
use AIArmada\Docs\States\Pending;
use AIArmada\FilamentDocs\FilamentDocsPlugin;
use AIArmada\FilamentDocs\Resources\DocResource\Pages\CreateDoc;
use AIArmada\FilamentDocs\Resources\DocResource\Pages\EditDoc;
use AIArmada\FilamentDocs\Resources\DocResource\Pages\ListDocs;
use AIArmada\FilamentDocs\Resources\DocResource\Pages\ViewDoc;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\ApprovalsRelationManager;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\EmailsRelationManager;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\PaymentsRelationManager;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\StatusHistoriesRelationManager;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\VersionsRelationManager;
use AIArmada\FilamentDocs\Resources\DocResource\Schemas\DocForm;
use AIArmada\FilamentDocs\Resources\DocResource\Schemas\DocInfolist;
use AIArmada\FilamentDocs\Resources\DocResource\Tables\DocsTable;
use AIArmada\FilamentDocs\Support\DocsOwnerScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class DocResource extends Resource
{
    protected static ?string $model = Doc::class;

    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'doc_number';

    protected static ?string $navigationLabel = 'Documents';

    protected static ?string $modelLabel = 'Document';

    protected static ?string $pluralModelLabel = 'Documents';

    public static function canViewAny(): bool
    {
        return FilamentPermission::hasAbility('purchase.viewAny');
    }

    public static function canView(Model $record): bool
    {
        return FilamentPermission::hasAbility('purchase.view');
    }

    public static function canCreate(): bool
    {
        return FilamentPermission::hasAnyAbility(['purchase.create', 'purchase.viewAny']);
    }

    public static function canEdit(Model $record): bool
    {
        return FilamentPermission::hasAnyAbility(['purchase.update', 'purchase.viewAny']);
    }

    public static function canDelete(Model $record): bool
    {
        return FilamentPermission::hasAnyAbility(['purchase.delete', 'purchase.viewAny']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return DocForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DocInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            StatusHistoriesRelationManager::class,
            PaymentsRelationManager::class,
            EmailsRelationManager::class,
            VersionsRelationManager::class,
            ApprovalsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocs::route('/'),
            'create' => CreateDoc::route('/create'),
            'view' => ViewDoc::route('/{record}'),
            'edit' => EditDoc::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()
            ->whereIn('status', [DocStatus::normalize(Pending::class), DocStatus::normalize(Overdue::class)])
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        $overdueCount = static::getEloquentQuery()
            ->where('status', DocStatus::normalize(Overdue::class))
            ->count();

        return $overdueCount > 0 ? 'danger' : 'warning';
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return app(FilamentDocsPlugin::class)->getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-docs.resources.navigation_sort.docs', 10);
    }

    /**
     * @return Builder<Doc>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Doc> $query */
        $query = parent::getEloquentQuery();

        return DocsOwnerScope::applyToDocs($query);
    }
}
