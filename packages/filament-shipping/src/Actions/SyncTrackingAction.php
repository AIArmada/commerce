<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Actions;

use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Services\BatchRateLimiter;
use AIArmada\Shipping\Services\TrackingAggregator;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class SyncTrackingAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('sync_tracking')
            ->label('Sync Tracking')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('info')
            ->visible(fn (Shipment $record): bool => $record->tracking_number !== null)
            ->authorize(fn (Shipment $record): bool => auth()->user()?->can('syncTracking', $record) ?? false)
            ->action(function (Shipment $record): void {
                try {
                    $aggregator = app(TrackingAggregator::class);
                    $updatedShipment = $aggregator->syncTracking($record);

                    Notification::make()
                        ->title('Tracking Updated')
                        ->body("Status: {$updatedShipment->status->label()}")
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    report($e);

                    Notification::make()
                        ->title('Tracking Sync Failed')
                        ->body('Unable to sync tracking. Please try again or check logs.')
                        ->danger()
                        ->send();
                }
            });
    }

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'sync_tracking');
    }

    public static function bulkAction(?string $name = null): BulkAction
    {
        return BulkAction::make($name ?? 'bulk_sync_tracking')
            ->label('Sync Tracking')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('info')
            ->deselectRecordsAfterCompletion()
            ->authorize(fn (): bool => auth()->user()?->can('shipping.shipments.sync-tracking') ?? false)
            ->action(function (Collection $records): void {
                $user = auth()->user();

                if ($user === null) {
                    Notification::make()
                        ->title('Authentication Required')
                        ->body('Please sign in to sync tracking.')
                        ->danger()
                        ->send();

                    return;
                }

                $aggregator = app(TrackingAggregator::class);

                $trackableShipments = $records->filter(
                    fn ($record) => $record instanceof Shipment
                        && $record->tracking_number !== null
                        && $user->can('syncTracking', $record)
                );

                if ($trackableShipments->isEmpty()) {
                    Notification::make()
                        ->title('No Trackable Shipments')
                        ->body('None of the selected records have tracking numbers.')
                        ->warning()
                        ->send();

                    return;
                }

                $byCarrier = $trackableShipments->groupBy('carrier_code');
                $successCount = 0;
                $failCount = 0;

                foreach ($byCarrier as $carrierCode => $shipments) {
                    $results = BatchRateLimiter::forCarrier($carrierCode)
                        ->execute(
                            $shipments,
                            fn (Shipment $shipment) => $aggregator->syncTracking($shipment),
                            'sync_tracking'
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
                        ->title('Tracking Updated')
                        ->body("{$successCount} shipment(s) updated successfully.")
                        ->success()
                        ->send();
                }

                if ($failCount > 0) {
                    Notification::make()
                        ->title('Some Updates Failed')
                        ->body("{$failCount} shipment(s) could not be updated.")
                        ->warning()
                        ->send();
                }
            });
    }
}
