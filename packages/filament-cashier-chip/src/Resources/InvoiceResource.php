<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources;

use AIArmada\Chip\Models\Purchase;
use AIArmada\FilamentCashierChip\Resources\InvoiceResource\Pages\ListInvoices;
use AIArmada\FilamentCashierChip\Resources\InvoiceResource\Pages\ViewInvoice;
use AIArmada\FilamentCashierChip\Resources\InvoiceResource\Schemas\InvoiceInfolist;
use AIArmada\FilamentCashierChip\Resources\InvoiceResource\Tables\InvoiceTable;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;

final class InvoiceResource extends BaseCashierChipResource
{
    protected static ?string $model = Purchase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $modelLabel = 'Invoice';

    protected static ?string $pluralModelLabel = 'Invoices';

    protected static ?string $recordTitleAttribute = 'reference';

    #[Override]
    public static function table(Table $table): Table
    {
        return InvoiceTable::configure($table);
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return InvoiceInfolist::configure($schema);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'reference',
            'reference_generated',
            'client->email',
            'client->full_name',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'view' => ViewInvoice::route('/{record}'),
        ];
    }

    protected static function navigationSortKey(): string
    {
        return 'invoices';
    }
}
