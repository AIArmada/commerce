<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources\CustomerResource\Pages;

use AIArmada\FilamentCashierChip\Resources\CustomerResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Override;

final class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    #[Override]
    public function getTitle(): string
    {
        $record = $this->getRecord();

        return sprintf(
            'Customer: %s',
            $record->getAttribute('name') ?? $record->getAttribute('email') ?? $record->getKey()
        );
    }

    public function getHeadingIcon(): Heroicon
    {
        return Heroicon::OutlinedUserCircle;
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('create_chip_customer')
                    ->label('Create in Chip')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Create Customer in Chip')
                    ->modalDescription('This will create this customer in the Chip payment gateway.')
                    ->visible(fn (): bool => ! method_exists($this->getRecord(), 'hasChipId') || ! $this->getRecord()->hasChipId())
                    ->action(function (): void {
                        $record = $this->getRecord();

                        if (method_exists($record, 'createAsChipCustomer')) {
                            $record->createAsChipCustomer();
                            $this->record = $record->fresh();

                            Notification::make()
                                ->title('Customer Created in Chip')
                                ->body('Chip ID: ' . ($this->chipCustomerId($record) ?? 'unknown'))
                                ->success()
                                ->send();
                        }
                    }),

                Action::make('sync_chip_customer')
                    ->label('Sync to Chip')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => method_exists($this->getRecord(), 'hasChipId') && $this->getRecord()->hasChipId())
                    ->action(function (): void {
                        $record = $this->getRecord();

                        if (method_exists($record, 'syncChipCustomerDetails')) {
                            $record->syncChipCustomerDetails();
                            $this->record = $record->fresh();

                            Notification::make()
                                ->title('Customer Synced to Chip')
                                ->success()
                                ->send();
                        }
                    }),

                Action::make('update_default_payment')
                    ->label('Refresh Payment Method')
                    ->icon('heroicon-o-credit-card')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => method_exists($this->getRecord(), 'hasChipId') && $this->getRecord()->hasChipId())
                    ->action(function (): void {
                        $record = $this->getRecord();

                        if (method_exists($record, 'updateDefaultPaymentMethodFromChip')) {
                            $record->updateDefaultPaymentMethodFromChip();
                            $this->record = $record->fresh();

                            Notification::make()
                                ->title('Payment Method Refreshed')
                                ->success()
                                ->send();
                        }
                    }),
            ])->label('Customer Actions'),

            Action::make('setup_payment_method')
                ->label('Add Payment Method')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->visible(fn (): bool => method_exists($this->getRecord(), 'hasChipId') && $this->getRecord()->hasChipId())
                ->action(function (): void {
                    $record = $this->getRecord();

                    if (method_exists($record, 'setupPaymentMethodUrl')) {
                        $url = $record->setupPaymentMethodUrl([
                            'success_url' => url()->current(),
                            'cancel_url' => url()->current(),
                        ]);

                        if ($url) {
                            Notification::make()
                                ->title('Payment Method Setup URL')
                                ->body('Redirect the customer to: ' . $url)
                                ->info()
                                ->persistent()
                                ->send();
                        }
                    }
                }),

            Action::make('view_in_chip')
                ->label('View in Chip')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->visible(fn (): bool => method_exists($this->getRecord(), 'hasChipId') && $this->getRecord()->hasChipId())
                ->url(fn (): string => 'https://app.chip-in.asia/clients/' . ($this->chipCustomerId($this->getRecord()) ?? ''))
                ->openUrlInNewTab(),
        ];
    }

    private function chipCustomerId(Model $record): ?string
    {
        if (! is_callable([$record, 'chipId'])) {
            return null;
        }

        $chipCustomerId = call_user_func([$record, 'chipId']);

        return is_string($chipCustomerId) && $chipCustomerId !== '' ? $chipCustomerId : null;
    }
}
