<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Actions;

use AIArmada\Shipping\Features\Shipments\Api\BulkShipmentOperations;
use AIArmada\Shipping\Features\Shipments\Api\ShipmentOperations;
use AIArmada\Shipping\Features\Shipments\Models\Shipment;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class ShipAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('ship')
            ->label('Ship')
            ->icon(Heroicon::OutlinedTruck)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Ship Package')
            ->modalDescription('This will create the shipment with the carrier and generate tracking.')
            ->visible(fn (Shipment $record): bool => $record->isPending())
            ->authorize(fn (Shipment $record): bool => auth()->user()?->can('ship', $record) ?? false)
            ->action(function (Shipment $record): void {
                try {
                    $operations = app(ShipmentOperations::class);
                    $operations->ship((string) $record->getKey());

                    $record->refresh();

                    Notification::make()
                        ->title('Shipment Created')
                        ->body("Tracking: {$record->tracking_number}")
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    report($e);

                    Notification::make()
                        ->title('Shipment Failed')
                        ->body('Unable to create shipment. Please try again or check logs.')
                        ->danger()
                        ->send();
                }
            });
    }

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'ship');
    }

    public static function bulkAction(?string $name = null): BulkAction
    {
        return BulkAction::make($name ?? 'bulk_ship')
            ->label('Ship Selected')
            ->icon(Heroicon::OutlinedTruck)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Ship Selected Packages')
            ->modalDescription('This will create shipments with carriers for all selected pending packages.')
            ->deselectRecordsAfterCompletion()
            ->authorize(fn (): bool => auth()->user()?->can('shipping.shipments.ship') ?? false)
            ->action(function (Collection $records): void {
                $user = auth()->user();

                if ($user === null) {
                    Notification::make()
                        ->title('Authentication Required')
                        ->body('Please sign in to ship shipments.')
                        ->danger()
                        ->send();

                    return;
                }

                $pendingShipments = $records->filter(
                    fn ($record) => $record instanceof Shipment
                        && $record->isPending()
                        && $user->can('ship', $record)
                );

                if ($pendingShipments->isEmpty()) {
                    Notification::make()
                        ->title('No Pending Shipments')
                        ->body('None of the selected records are pending shipments.')
                        ->warning()
                        ->send();

                    return;
                }

                $result = app(BulkShipmentOperations::class)->ship(
                    $pendingShipments->map(fn (Shipment $shipment): string => (string) $shipment->getKey())->values()->all(),
                );
                $successCount = $result->succeeded;
                $failCount = $result->failed;

                if ($successCount > 0) {
                    Notification::make()
                        ->title('Shipments Created')
                        ->body("{$successCount} shipment(s) created successfully.")
                        ->success()
                        ->send();
                }

                if ($failCount > 0) {
                    Notification::make()
                        ->title('Some Shipments Failed')
                        ->body("{$failCount} shipment(s) failed to create.")
                        ->warning()
                        ->send();
                }
            });
    }
}
