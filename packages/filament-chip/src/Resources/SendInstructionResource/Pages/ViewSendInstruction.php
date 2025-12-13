<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\SendInstructionResource\Pages;

use AIArmada\Chip\Services\ChipSendService;
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

        return sprintf('Payout %s', $record->reference ?? $record->getKey());
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
                    $service = app(ChipSendService::class);

                    try {
                        $service->resendSendInstructionWebhook((string) $record->id);
                        Notification::make()
                            ->title('Webhook resent successfully')
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Failed to resend webhook')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn (): bool => in_array($this->getRecord()->state, ['completed', 'processed', 'failed'], true)),

            Actions\Action::make('cancel')
                ->label('Cancel Payout')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cancel Payout')
                ->modalDescription('Are you sure you want to cancel this payout? This action cannot be undone.')
                ->action(function (): void {
                    $record = $this->getRecord();
                    $service = app(ChipSendService::class);

                    try {
                        $service->cancelSendInstruction((string) $record->id);
                        Notification::make()
                            ->title('Payout cancelled successfully')
                            ->success()
                            ->send();
                        $this->refreshFormData(['state']);
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Failed to cancel payout')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn (): bool => in_array($this->getRecord()->state, ['queued', 'received', 'verifying'], true)),
        ];
    }
}
