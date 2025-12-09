<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources;

use AIArmada\Docs\Models\DocTemplate;
use AIArmada\FilamentDocs\Resources\DocTemplateResource\Pages\CreateDocTemplate;
use AIArmada\FilamentDocs\Resources\DocTemplateResource\Pages\EditDocTemplate;
use AIArmada\FilamentDocs\Resources\DocTemplateResource\Pages\ListDocTemplates;
use AIArmada\FilamentDocs\Resources\DocTemplateResource\Pages\ViewDocTemplate;
use AIArmada\FilamentDocs\Resources\DocTemplateResource\Schemas\DocTemplateForm;
use AIArmada\FilamentDocs\Resources\DocTemplateResource\Schemas\DocTemplateInfolist;
use AIArmada\FilamentDocs\Resources\DocTemplateResource\Tables\DocTemplatesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class DocTemplateResource extends Resource
{
    protected static ?string $model = DocTemplate::class;

    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Templates';

    protected static ?string $modelLabel = 'Template';

    protected static ?string $pluralModelLabel = 'Templates';

    public static function form(Schema $schema): Schema
    {
        return DocTemplateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DocTemplateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocTemplates::route('/'),
            'create' => CreateDocTemplate::route('/create'),
            'view' => ViewDocTemplate::route('/{record}'),
            'edit' => EditDocTemplate::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getModel()::count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'gray';
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-docs.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-docs.resources.navigation_sort.doc_templates', 20);
    }
}
