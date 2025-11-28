<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources;

use AIArmada\Docs\Models\Doc;
use AIArmada\FilamentDocs\Resources\DocResource\Pages\CreateDoc;
use AIArmada\FilamentDocs\Resources\DocResource\Pages\EditDoc;
use AIArmada\FilamentDocs\Resources\DocResource\Pages\ListDocs;
use AIArmada\FilamentDocs\Resources\DocResource\Pages\ViewDoc;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\StatusHistoriesRelationManager;
use AIArmada\FilamentDocs\Resources\DocResource\Schemas\DocForm;
use AIArmada\FilamentDocs\Resources\DocResource\Schemas\DocInfolist;
use AIArmada\FilamentDocs\Resources\DocResource\Tables\DocsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class DocResource extends Resource
{
    protected static ?string $model = Doc::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'doc_number';

    protected static ?string $navigationLabel = 'Documents';

    protected static ?string $modelLabel = 'Document';

    protected static ?string $pluralModelLabel = 'Documents';

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
        $count = self::getModel()::count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'primary';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('filament-docs.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-docs.resources.navigation_sort.docs', 10);
    }
}
