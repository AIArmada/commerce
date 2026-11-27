<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources\CustomerResource\Pages;

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CommerceSupport\Data\OwnerJobContext;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerJobContextMapper;
use AIArmada\FilamentCashierChip\EntryPoints\Jobs\SyncCustomersToChipJob;
use AIArmada\FilamentCashierChip\Resources\CustomerResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    #[Override]
    public function getTitle(): string
    {
        return 'Customers';
    }

    #[Override]
    public function getSubheading(): string
    {
        return 'Manage billable customers and their payment information.';
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_all_to_chip')
                ->label('Sync All to Chip')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Sync All Customers to Chip')
                ->modalDescription('This will queue a background job to create or update all customers in Chip that are not yet linked.')
                ->action(function (): void {
                    $model = Cashier::$customerModel;

                    if (! method_exists($model, 'chipCustomerLink') || ! method_exists($model, 'createAsChipCustomer')) {
                        Notification::make()
                            ->title('Sync Unavailable')
                            ->body('The configured customer model does not support CHIP syncing.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $context = self::ownerJobContext();

                    if ((bool) config('cashier-chip.features.owner.enabled', false) && $context === null) {
                        Notification::make()
                            ->title('Sync Unavailable')
                            ->body('Select an owner before syncing customers.')
                            ->danger()
                            ->send();

                        return;
                    }

                    SyncCustomersToChipJob::dispatch($context);

                    Notification::make()
                        ->title('Customer Sync Queued')
                        ->body('Unlinked customers will be synced to Chip in the background.')
                        ->success()
                        ->send();
                }),
        ];
    }

    private static function ownerJobContext(): ?OwnerJobContext
    {
        $owner = OwnerContext::resolve();

        if ($owner !== null) {
            return OwnerJobContextMapper::fromModel($owner);
        }

        if (OwnerContext::isExplicitGlobal()) {
            return OwnerJobContext::explicitGlobal();
        }

        return null;
    }
}
