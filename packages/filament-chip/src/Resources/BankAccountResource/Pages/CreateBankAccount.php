<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\BankAccountResource\Pages;

use AIArmada\Chip\Features\Payouts\Actions\CreateBankAccount as CreateBankAccountAction;
use AIArmada\FilamentChip\Resources\BankAccountResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Override;
use Throwable;

final class CreateBankAccount extends CreateRecord
{
    protected static string $resource = BankAccountResource::class;

    #[Override]
    public function getTitle(): string
    {
        return 'Add Bank Account';
    }

    #[Override]
    public function getSubheading(): string
    {
        return 'Register a new bank account for receiving payouts via CHIP Send.';
    }

    /** @param array<string, mixed> $data */
    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateBankAccountAction::class)->handle(
                bankCode: (string) $data['bank_code'],
                accountNumber: (string) $data['account_number'],
                accountHolderName: (string) $data['name'],
                reference: (string) $data['reference'],
            );
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Failed to create bank account')
                ->body('The request could not be completed. Check its status before retrying.')
                ->danger()
                ->send();

            throw new Halt;
        }
    }

    #[Override]
    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
