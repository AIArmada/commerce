<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\BankAccountResource\Pages;

use AIArmada\Chip\Models\BankAccount;
use AIArmada\Chip\Services\ChipSendService;
use AIArmada\FilamentChip\Resources\BankAccountResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Override;
use Throwable;

final class ViewBankAccount extends ViewRecord
{
    protected static string $resource = BankAccountResource::class;

    #[Override]
    public function getTitle(): string
    {
        $record = $this->getRecord();

        return sprintf('Bank Account: %s', (string) ($record->getAttribute('name') ?? $record->getKey()));
    }

    public function getHeadingIcon(): Heroicon
    {
        return Heroicon::OutlinedBuildingLibrary;
    }

    /**
     * @return array<Actions\Action>
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('verify')
                ->label('Request Verification')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Request Verification')
                ->modalDescription('This will submit the bank account for verification with CHIP.')
                ->action(function (): void {
                    $record = $this->getRecord();
                    $service = app(ChipSendService::class);
                    $scopedRecord = $this->resolveScopedBankAccount($record);

                    if ($scopedRecord === null) {
                        Notification::make()
                            ->title('Bank account is outside your owner scope')
                            ->danger()
                            ->send();

                        return;
                    }

                    try {
                        $service->updateBankAccount((string) $scopedRecord->getKey(), [
                            'status' => 'verifying',
                        ]);
                        Notification::make()
                            ->title('Verification requested')
                            ->success()
                            ->send();
                        $this->refreshFormData(['status']);
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Failed to request verification')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn (): bool => (string) $this->getRecord()->getAttribute('status') === 'pending'),

            Actions\Action::make('disable')
                ->label('Disable Account')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Disable Bank Account')
                ->modalDescription('This will disable the bank account. It cannot be used for payouts until re-enabled.')
                ->action(function (): void {
                    $record = $this->getRecord();
                    $service = app(ChipSendService::class);
                    $scopedRecord = $this->resolveScopedBankAccount($record);

                    if ($scopedRecord === null) {
                        Notification::make()
                            ->title('Bank account is outside your owner scope')
                            ->danger()
                            ->send();

                        return;
                    }

                    try {
                        $service->deleteBankAccount((string) $scopedRecord->getKey());
                        Notification::make()
                            ->title('Bank account disabled')
                            ->success()
                            ->send();
                        $this->refreshFormData(['status']);
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Failed to disable account')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn (): bool => in_array((string) $this->getRecord()->getAttribute('status'), ['active', 'approved'], true)),
        ];
    }

    private function resolveScopedBankAccount(Model $record): ?BankAccount
    {
        return BankAccount::query()
            ->forOwner()
            ->whereKey($record->getKey())
            ->first();
    }
}
