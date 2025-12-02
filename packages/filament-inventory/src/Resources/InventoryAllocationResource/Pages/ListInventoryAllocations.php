<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryAllocationResource\Pages;

use AIArmada\FilamentInventory\Resources\InventoryAllocationResource;
use AIArmada\Inventory\Facades\InventoryAllocation;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

final class ListInventoryAllocations extends ListRecords
{
    protected static string $resource = InventoryAllocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cleanup_expired')
                ->label('Cleanup Expired')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cleanup Expired Allocations')
                ->modalDescription('This will release all expired allocations and restore stock to available inventory.')
                ->action(function (): void {
                    $count = InventoryAllocation::cleanupExpired();

                    if ($count > 0) {
                        $this->sendSuccessNotification("Released {$count} expired allocations.");
                    } else {
                        $this->sendWarningNotification('No expired allocations to cleanup.');
                    }
                }),
        ];
    }

    protected function sendSuccessNotification(string $message): void
    {
        \Filament\Notifications\Notification::make()
            ->title('Success')
            ->body($message)
            ->success()
            ->send();
    }

    protected function sendWarningNotification(string $message): void
    {
        \Filament\Notifications\Notification::make()
            ->title('Info')
            ->body($message)
            ->warning()
            ->send();
    }
}
