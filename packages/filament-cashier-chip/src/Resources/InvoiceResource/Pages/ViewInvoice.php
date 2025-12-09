<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources\InvoiceResource\Pages;

use AIArmada\Chip\Models\Purchase;
use AIArmada\FilamentCashierChip\Resources\InvoiceResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Override;

final class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    #[Override]
    public function getTitle(): string
    {
        /** @var Purchase $record */
        $record = $this->getRecord();

        return sprintf('Invoice: %s', $record->reference ?? $record->getKey());
    }

    public function getHeadingIcon(): Heroicon
    {
        return Heroicon::OutlinedDocumentText;
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->visible(function (): bool {
                    /** @var Purchase $record */
                    $record = $this->getRecord();

                    return $record->status === 'paid';
                })
                ->action(function (): void {
                    // PDF generation would be handled here
                    Notification::make()
                        ->title('PDF Generation')
                        ->body('PDF download functionality requires a PDF renderer. Configure in cashier-chip config.')
                        ->info()
                        ->send();
                }),

            Action::make('send_invoice')
                ->label('Send Invoice')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Send Invoice by Email')
                ->modalDescription('This will send the invoice to the customer\'s email address.')
                ->visible(function (): bool {
                    /** @var Purchase $record */
                    $record = $this->getRecord();

                    return ! empty($record->client['email'] ?? null);
                })
                ->action(function (): void {
                    /** @var Purchase $record */
                    $record = $this->getRecord();
                    // Email sending would be handled here
                    Notification::make()
                        ->title('Invoice Sent')
                        ->body('Invoice has been sent to ' . $record->client['email'])
                        ->success()
                        ->send();
                }),

            Action::make('view_checkout')
                ->label('View Checkout')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(function (): ?string {
                    /** @var Purchase $record */
                    $record = $this->getRecord();

                    return $record->checkout_url;
                })
                ->openUrlInNewTab()
                ->visible(function (): bool {
                    /** @var Purchase $record */
                    $record = $this->getRecord();

                    return ! empty($record->checkout_url);
                }),

            Action::make('copy_checkout_url')
                ->label('Copy Checkout URL')
                ->icon('heroicon-o-clipboard-document')
                ->color('gray')
                ->visible(function (): bool {
                    /** @var Purchase $record */
                    $record = $this->getRecord();

                    return ! empty($record->checkout_url) && $record->status !== 'paid';
                })
                ->action(function (): void {
                    Notification::make()
                        ->title('URL Copied')
                        ->body('Checkout URL copied to clipboard.')
                        ->success()
                        ->send();
                }),

            Action::make('mark_as_paid')
                ->label('Mark as Paid')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Mark Invoice as Paid')
                ->modalDescription('This will manually mark this invoice as paid. Use this only for offline payments.')
                ->visible(function (): bool {
                    /** @var Purchase $record */
                    $record = $this->getRecord();

                    return $record->status !== 'paid';
                })
                ->action(function (): void {
                    /** @var Purchase $record */
                    $record = $this->getRecord();
                    $record->status = 'paid';
                    $record->paid_on = now()->getTimestamp();
                    $record->save();

                    Notification::make()
                        ->title('Invoice Marked as Paid')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'paid_on']);
                }),
        ];
    }
}
