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
                    $operations = app(ShipmentOperations::class);
                    $operations->syncTracking((string) $record->getKey());
                    $record->refresh();

                    Notification::make()
                        ->title('Tracking Updated')
                        ->body("Status: {$record->status->label()}")
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

                $result = app(BulkShipmentOperations::class)->syncTracking(
                    $trackableShipments->map(fn (Shipment $shipment): string => (string) $shipment->getKey())->values()->all(),
                );
                $successCount = $result->succeeded;
                $failCount = $result->failed;

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
