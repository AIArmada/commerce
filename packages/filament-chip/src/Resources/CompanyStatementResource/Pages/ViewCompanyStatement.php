<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\CompanyStatementResource\Pages;

use AIArmada\Chip\Models\CompanyStatement;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\CommerceSupport\Filament\Pages\ReadOnlyViewRecord;
use AIArmada\FilamentChip\Resources\CompanyStatementResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
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
                    $scoped = $this->resolveScopedCompanyStatement($this->getRecord());

                    if ($scoped === null) {
                        Notification::make()
                            ->title('Statement is outside your owner scope')
                            ->danger()
                            ->send();

                        return;
                    }

                    $service = app(ChipCollectService::class);

                    try {
                        $statement = $service->getCompanyStatement((string) $scoped->getKey());
                        $downloadUrl = $statement->download_url;

                        if (is_string($downloadUrl) && $downloadUrl !== '') {
                            redirect()->away($downloadUrl);
                        } else {
                            Notification::make()
                                ->title('Download not available')
                                ->body('Statement is not ready for download yet.')
                                ->warning()
                                ->send();
                        }
                    } catch (Throwable $e) {
                        Log::warning('filament-chip: company statement download failed', [
                            'exception' => $e->getMessage(),
                        ]);

                        Notification::make()
                            ->title('Failed to fetch statement')
                            ->body('The statement service did not respond. Please try again later.')
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn (): bool => in_array((string) $this->getRecord()->getAttribute('status'), ['completed', 'ready'], true)),

            Actions\Action::make('cancel')
                ->label('Cancel Request')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cancel Statement Request')
                ->modalDescription('Are you sure you want to cancel this statement request?')
                ->action(function (): void {
                    $scoped = $this->resolveScopedCompanyStatement($this->getRecord());

                    if ($scoped === null) {
                        Notification::make()
                            ->title('Statement is outside your owner scope')
                            ->danger()
                            ->send();

                        return;
                    }

                    $service = app(ChipCollectService::class);

                    try {
                        $service->cancelCompanyStatement((string) $scoped->getKey());
                        Notification::make()
                            ->title('Statement request cancelled')
                            ->success()
                            ->send();
                        $this->refreshFormData(['status']);
                    } catch (Throwable $e) {
                        Log::warning('filament-chip: company statement cancellation failed', [
                            'exception' => $e->getMessage(),
                        ]);

                        Notification::make()
                            ->title('Failed to cancel statement')
                            ->body('The statement service did not respond. Please try again later.')
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn (): bool => in_array((string) $this->getRecord()->getAttribute('status'), ['queued', 'processing'], true)),
        ];
    }

    private function resolveScopedCompanyStatement(Model $record): ?CompanyStatement
    {
        return CompanyStatement::query()
            ->forOwner()
            ->whereKey($record->getKey())
            ->first();
    }
}
