<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Actions;

use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Services\BatchRateLimiter;
use AIArmada\Shipping\Services\ShipmentService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class CancelShipmentAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('cancel')
            ->label('Cancel')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Cancel Shipment')
            ->modalDescription('Are you sure you want to cancel this shipment? This action cannot be undone.')
            ->visible(fn (Shipment $record): bool => $record->isCancellable())
            ->authorize(fn (Shipment $record): bool => auth()->user()?->can('cancel', $record) ?? false)
            ->action(function (Shipment $record): void {
                try {
                    $shipmentService = app(ShipmentService::class);
                    $shipmentService->cancel($record);

                    Notification::make()
                        ->title('Shipment Cancelled')
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    report($e);

                    Notification::make()
                        ->title('Cancellation Failed')
                        ->body('Unable to cancel shipment. Please try again or check logs.')
                        ->danger()
                        ->send();
                }
            });
    }

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'cancel');
    }

    public static function bulkAction(?string $name = null): BulkAction
    {
        return BulkAction::make($name ?? 'bulk_cancel')
            ->label('Cancel Selected')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Cancel Selected Shipments')
            ->modalDescription('Are you sure you want to cancel all selected shipments? This action cannot be undone.')
            ->deselectRecordsAfterCompletion()
            ->authorize(fn (): bool => auth()->user()?->can('shipping.shipments.cancel') ?? false)
            ->action(function (Collection $records): void {
                $user = auth()->user();

                if ($user === null) {
                    Notification::make()
                        ->title('Authentication Required')
                        ->body('Please sign in to cancel shipments.')
                        ->danger()
                        ->send();

                    return;
                }

                $shipmentService = app(ShipmentService::class);

                $cancellableShipments = $records->filter(
                    fn ($record) => $record instanceof Shipment
                    && $record->isCancellable()
                    && $user->can('cancel', $record)
                );

                if ($cancellableShipments->isEmpty()) {
                    Notification::make()
                        ->title('No Cancellable Shipments')
                        ->body('None of the selected records can be cancelled.')
                        ->warning()
                        ->send();

                    return;
                }

                $byCarrier = $cancellableShipments->groupBy('carrier_code');
                $successCount = 0;
                $failCount = 0;

                foreach ($byCarrier as $carrierCode => $shipments) {
                    $results = BatchRateLimiter::forCarrier($carrierCode)
                        ->execute(
                            $shipments,
                            fn (Shipment $shipment) => $shipmentService->cancel($shipment),
                            'cancel'
                        );

                    foreach ($results as $result) {
                        if ($result['success']) {
                            $successCount++;
                        } else {
                            $failCount++;
                        }
                    }
                }

                if ($successCount > 0) {
                    Notification::make()
                        ->title('Shipments Cancelled')
                        ->body("{$successCount} shipment(s) cancelled successfully.")
                        ->success()
                        ->send();
                }

                if ($failCount > 0) {
                    Notification::make()
                        ->title('Some Cancellations Failed')
                        ->body("{$failCount} shipment(s) could not be cancelled.")
                        ->warning()
                        ->send();
                }
            });
    }
}
