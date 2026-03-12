<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources;

use AIArmada\Chip\Models\CompanyStatement;
use AIArmada\FilamentChip\Resources\CompanyStatementResource\Pages;
use AIArmada\FilamentChip\Resources\CompanyStatementResource\Schemas\CompanyStatementInfolist;
use AIArmada\FilamentChip\Resources\CompanyStatementResource\Tables\CompanyStatementTable;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;

final class CompanyStatementResource extends BaseChipResource
{
    protected static ?string $model = CompanyStatement::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Statements';

    protected static ?string $modelLabel = 'Company Statement';

    protected static ?string $pluralModelLabel = 'Company Statements';

    protected static ?string $recordTitleAttribute = 'id';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return CompanyStatementTable::configure($table);
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return CompanyStatementInfolist::configure($schema);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanyStatements::route('/'),
            'view' => Pages\ViewCompanyStatement::route('/{record}'),
        ];
    }

    protected static function navigationSortKey(): string
    {
        return 'company_statements';
    }
}
