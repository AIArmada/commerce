<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\BankAccountResource\Pages;

use AIArmada\Chip\Features\Payouts\Actions\DeleteBankAccount;
use AIArmada\FilamentChip\Resources\BankAccountResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
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

                    try {
                        app(DeleteBankAccount::class)->handle((int) $record->getKey());
                        Notification::make()
                            ->title('Bank account deleted')
                            ->success()
                            ->send();
                        $this->redirect(self::getResource()::getUrl('index'));
                    } catch (Throwable $e) {
                        report($e);

                        Notification::make()
                            ->title('Failed to delete account')
                            ->body('The operation could not be completed. Verify its status before retrying.')
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn (): bool => $this->getRecord()->getAttribute('deleted_at') === null),
        ];
    }
}
