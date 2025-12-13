<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\CompanyStatementResource\Pages;

use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\FilamentChip\Resources\CompanyStatementResource;
use AIArmada\FilamentChip\Resources\Pages\ReadOnlyViewRecord;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Override;
use Throwable;

final class ViewCompanyStatement extends ReadOnlyViewRecord
{
    protected static string $resource = CompanyStatementResource::class;

    #[Override]
    public function getTitle(): string
    {
        $record = $this->getRecord();

        return sprintf('Statement %s', $record->getKey());
    }

    public function getHeadingIcon(): Heroicon
    {
        return Heroicon::OutlinedDocumentText;
    }

    /**
     * @return array<Actions\Action>
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download')
                ->label('Download Statement')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->action(function (): void {
                    $record = $this->getRecord();
                    $service = app(ChipCollectService::class);

                    try {
                        $statement = $service->getCompanyStatement($record->id);
                        $downloadUrl = $statement->download_url ?? null;

                        if ($downloadUrl) {
                            redirect()->away($downloadUrl);
                        } else {
                            Notification::make()
                                ->title('Download not available')
                                ->body('Statement is not ready for download yet.')
                                ->warning()
                                ->send();
                        }
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Failed to fetch statement')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn (): bool => in_array($this->getRecord()->status, ['completed', 'ready'], true)),

            Actions\Action::make('cancel')
                ->label('Cancel Request')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cancel Statement Request')
                ->modalDescription('Are you sure you want to cancel this statement request?')
                ->action(function (): void {
                    $record = $this->getRecord();
                    $service = app(ChipCollectService::class);

                    try {
                        $service->cancelCompanyStatement($record->id);
                        Notification::make()
                            ->title('Statement request cancelled')
                            ->success()
                            ->send();
                        $this->refreshFormData(['status']);
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Failed to cancel statement')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn (): bool => in_array($this->getRecord()->status, ['queued', 'processing'], true)),
        ];
    }
}
