<?php

declare(strict_types=1);

namespace AIArmada\FilamentStock\Actions;

use AIArmada\Stock\Models\StockReservation;
use AIArmada\Stock\Services\StockReservationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ReleaseReservationAction
{
    public static function make(): Action
    {
        return Action::make('release_reservation')
            ->label('Release')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Release Stock Reservation')
            ->modalDescription('Are you sure you want to release this reservation? The reserved stock will become available again.')
            ->modalSubmitActionLabel('Release')
            ->action(function (StockReservation $record): void {
                try {
                    $stockable = $record->stockable;

                    if (! $stockable) {
                        // Just delete the orphaned reservation
                        $record->delete();

                        Notification::make()
                            ->success()
                            ->title('Reservation Deleted')
                            ->body('The orphaned reservation has been removed.')
                            ->icon(Heroicon::OutlinedCheckCircle)
                            ->send();

                        return;
                    }

                    $service = app(StockReservationService::class);
                    $released = $service->release($stockable, $record->cart_id);

                    if ($released) {
                        Notification::make()
                            ->success()
                            ->title('Reservation Released')
                            ->body("Released {$record->quantity} units. Stock is now available.")
                            ->icon(Heroicon::OutlinedCheckCircle)
                            ->send();
                    } else {
                        Notification::make()
                            ->warning()
                            ->title('Release Failed')
                            ->body('The reservation could not be released. It may have already been released or expired.')
                            ->icon(Heroicon::OutlinedExclamationCircle)
                            ->send();
                    }

                } catch (Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Unexpected Error')
                        ->body('An unexpected error occurred while releasing the reservation.')
                        ->icon(Heroicon::OutlinedXCircle)
                        ->persistent()
                        ->send();

                    Log::error('Failed to release stock reservation', [
                        'reservation_id' => $record->id,
                        'error' => $exception->getMessage(),
                        'trace' => $exception->getTraceAsString(),
                    ]);
                }
            });
    }
}
