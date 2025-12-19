<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources\CustomerResource\Pages;

use AIArmada\CashierChip\Cashier;
use AIArmada\FilamentCashierChip\Resources\CustomerResource;
use AIArmada\FilamentCashierChip\Support\CashierChipOwnerScope;
use Exception;
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
                ->modalDescription('This will create or update all customers in Chip that are not yet linked. This may take some time.')
                ->action(function (): void {
                    $model = Cashier::$customerModel;
                    $customers = CashierChipOwnerScope::apply($model::query())
                        ->whereNull('chip_id')
                        ->get();
                    $synced = 0;
                    $failed = 0;

                    foreach ($customers as $customer) {
                        try {
                            if (method_exists($customer, 'createAsChipCustomer')) {
                                $customer->createAsChipCustomer();
                                $synced++;
                            }
                        } catch (Exception $e) {
                            $failed++;
                        }
                    }

                    Notification::make()
                        ->title('Customers Synced')
                        ->body("Synced: {$synced}, Failed: {$failed}")
                        ->success()
                        ->send();
                }),
        ];
    }
}
