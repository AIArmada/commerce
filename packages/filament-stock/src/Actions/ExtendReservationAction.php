<?php

declare(strict_types=1);

namespace AIArmada\FilamentStock\Actions;

use AIArmada\Stock\Models\StockReservation;
use AIArmada\Stock\Services\StockReservationService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ExtendReservationAction
{
    public static function make(): Action
    {
        return Action::make('extend_reservation')
            ->label('Extend Reservation')
            ->icon(Heroicon::OutlinedClock)
            ->color('info')
            ->modalHeading('Extend Stock Reservation')
            ->modalDescription('Extend the expiry time of this reservation.')
            ->modalSubmitActionLabel('Extend')
            ->visible(fn (StockReservation $record): bool => $record->isValid())
            ->form([
                TextInput::make('minutes')
                    ->label('Extend by (minutes)')
                    ->numeric()
                    ->required()
                    ->default(30)
                    ->minValue(1)
                    ->maxValue(1440)
                    ->helperText('How many minutes to extend the reservation (max 24 hours).'),
            ])
            ->action(function (array $data, StockReservation $record): void {
                try {
                    $minutes = (int) $data['minutes'];
                    $stockable = $record->stockable;

                    if (! $stockable) {
                        Notification::make()
                            ->warning()
                            ->title('Stockable Not Found')
                            ->body('The associated stockable item could not be found.')
                            ->icon(Heroicon::OutlinedExclamationCircle)
                            ->send();

                        return;
                    }

                    $service = app(StockReservationService::class);
                    $updated = $service->extend($stockable, $record->cart_id, $minutes);

                    if ($updated === null) {
                        Notification::make()
                            ->warning()
                            ->title('Extension Failed')
                            ->body('The reservation could not be extended. It may have already expired.')
                            ->icon(Heroicon::OutlinedExclamationCircle)
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title('Reservation Extended')
                        ->body("The reservation has been extended by {$minutes} minutes. New expiry: {$updated->expires_at->format('M d, Y g:i A')}")
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->send();

                } catch (Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Unexpected Error')
                        ->body('An unexpected error occurred while extending the reservation.')
                        ->icon(Heroicon::OutlinedXCircle)
                        ->persistent()
                        ->send();

                    Log::error('Failed to extend stock reservation', [
                        'reservation_id' => $record->id,
                        'error' => $exception->getMessage(),
                        'trace' => $exception->getTraceAsString(),
                    ]);
                }
            });
    }
}
