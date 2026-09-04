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
            Actions\Action::make('delete')
                ->label('Delete Account')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Delete Bank Account')
                ->modalDescription('This will delete the bank account from CHIP Send. This cannot be undone.')
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
                        $service->deleteBankAccount((int) $scopedRecord->getKey());
                        Notification::make()
                            ->title('Bank account deleted')
                            ->success()
                            ->send();
                        $this->redirect(self::getResource()::getUrl('index'));
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Failed to delete account')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn (): bool => $this->getRecord()->getAttribute('deleted_at') === null),
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
