<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\BankAccountResource\Pages;

use AIArmada\Chip\Services\ChipSendService;
use AIArmada\FilamentChip\Resources\BankAccountResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $service = app(ChipSendService::class);

        try {
            $bankAccount = $service->createBankAccount(
                bankCode: $data['bank_code'],
                accountNumber: $data['account_number'],
                accountHolderName: $data['name'],
                reference: $data['reference'] ?? null,
            );

            Notification::make()
                ->title('Bank account created successfully')
                ->body(sprintf('Account ID: %s', $bankAccount->id))
                ->success()
                ->send();

            return array_merge($data, [
                'id' => $bankAccount->id,
                'status' => $bankAccount->status,
            ]);
        } catch (Throwable $e) {
            Notification::make()
                ->title('Failed to create bank account')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();

            return $data;
        }
    }

    #[Override]
    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
