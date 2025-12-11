<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Actions;

use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Services\TrackingAggregator;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
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
            ->action(function (Shipment $record): void {
                try {
                    $aggregator = app(TrackingAggregator::class);
                    $updatedShipment = $aggregator->syncTracking($record);

                    Notification::make()
                        ->title('Tracking Updated')
                        ->body("Status: {$updatedShipment->status->getLabel()}")
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('Tracking Sync Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'sync_tracking');
    }
}
