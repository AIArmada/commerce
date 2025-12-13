<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources;

use AIArmada\Chip\Models\BankAccount;
use AIArmada\FilamentChip\Resources\BankAccountResource\Pages;
use AIArmada\FilamentChip\Resources\BankAccountResource\Schemas\BankAccountForm;
use AIArmada\FilamentChip\Resources\BankAccountResource\Schemas\BankAccountInfolist;
use AIArmada\FilamentChip\Resources\BankAccountResource\Tables\BankAccountTable;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;

final class BankAccountResource extends BaseChipResource
{
    protected static ?string $model = BankAccount::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $navigationLabel = 'Bank Accounts';

    protected static ?string $modelLabel = 'Bank Account';

    protected static ?string $pluralModelLabel = 'Bank Accounts';

    protected static ?string $recordTitleAttribute = 'name';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return BankAccountForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return BankAccountTable::configure($table);
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return BankAccountInfolist::configure($schema);
    }

    /**
     * @return array<string, \Filament\Resources\Pages\PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankAccounts::route('/'),
            'create' => Pages\CreateBankAccount::route('/create'),
            'view' => Pages\ViewBankAccount::route('/{record}'),
        ];
    }

    protected static function navigationSortKey(): string
    {
        return 'bank_accounts';
    }
}
