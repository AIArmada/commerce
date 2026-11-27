<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\SendInstructionResource\Pages;

use AIArmada\Chip\Features\Payouts\Actions\ResendPayoutWebhook;
use AIArmada\FilamentChip\Resources\SendInstructionResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Override;
use Throwable;

final class ViewSendInstruction extends ViewRecord
{
    protected static string $resource = SendInstructionResource::class;

    #[Override]
    public function getTitle(): string
    {
        $record = $this->getRecord();

        return sprintf('Payout %s', (string) ($record->getAttribute('reference') ?? $record->getKey()));
    }

    public function getHeadingIcon(): Heroicon
    {
        return Heroicon::OutlinedBanknotes;
    }

    /**
     * @return array<Actions\Action>
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('resend_webhook')
                ->label('Resend Webhook')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Resend Webhook')
                ->modalDescription('This will resend the webhook notification for this payout. Continue?')
                ->action(function (): void {
                    $record = $this->getRecord();

                    try {
                        app(ResendPayoutWebhook::class)->handle((int) $record->getKey());
                        Notification::make()
                            ->title('Webhook resent successfully')
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        report($e);

                        Notification::make()
                            ->title('Failed to resend webhook')
                            ->body('The operation could not be completed. Verify its status before retrying.')
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn (): bool => true),
        ];
    }
}
