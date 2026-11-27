<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources\InvoiceResource\Pages;

use AIArmada\Chip\Features\Collect\Api\PurchaseInvoiceOperations;
use AIArmada\Chip\Features\Collect\Models\Purchase;
use AIArmada\FilamentCashierChip\Resources\InvoiceResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Override;
use Throwable;

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

                    try {
                        app(PurchaseInvoiceOperations::class)->resend((string) $record->getKey());

                        Notification::make()->title('Invoice resend requested')->success()->send();
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()->title('Unable to resend invoice')->danger()->send();
                    }
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

                    try {
                        app(PurchaseInvoiceOperations::class)->markPaid((string) $record->getKey());

                        Notification::make()->title('Invoice Marked as Paid')->success()->send();
                        $this->refreshFormData(['status', 'marked_as_paid']);
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()->title('Unable to confirm payment')->danger()->send();
                    }
                }),
        ];
    }
}
