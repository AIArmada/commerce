<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\BankAccountResource\Pages;

use AIArmada\FilamentChip\Resources\BankAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListBankAccounts extends ListRecords
{
    protected static string $resource = BankAccountResource::class;

    #[Override]
    public function getTitle(): string
    {
        return 'Bank Accounts';
    }

    #[Override]
    public function getSubheading(): string
    {
        return 'Manage recipient bank accounts for CHIP Send payouts.';
    }

    /**
     * @return array<Actions\Action>
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Bank Account')
                ->icon('heroicon-o-plus'),
        ];
    }
}
