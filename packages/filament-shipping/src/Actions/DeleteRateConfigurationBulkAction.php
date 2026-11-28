<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Actions;

use AIArmada\Shipping\Features\Rates\Api\ManageRateConfiguration;
use AIArmada\Shipping\Features\Rates\Models\ShippingRate;
use AIArmada\Shipping\Features\Rates\Models\ShippingZone;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Throwable;

final class DeleteRateConfigurationBulkAction
{
    public static function make(bool $zones = false): BulkAction
    {
        return BulkAction::make('delete')
            ->label('Delete selected')
            ->color('danger')
            ->icon('heroicon-o-trash')
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion()
            ->action(function ($records) use ($zones): void {
                $api = app(ManageRateConfiguration::class);
                $deleted = 0;
                $failed = 0;

                foreach ($records as $record) {
                    if (($zones && ! $record instanceof ShippingZone)
                        || (! $zones && ! $record instanceof ShippingRate)
                        || Gate::denies('delete', $record)) {
                        $failed++;

                        continue;
                    }

                    try {
                        $success = $zones
                            ? $api->deleteZone((string) $record->getKey())
                            : $api->deleteRate((string) $record->getKey());

                        $success ? $deleted++ : $failed++;
                    } catch (Throwable $e) {
                        report($e);
                        $failed++;
                    }
                }

                $notification = Notification::make()
                    ->title('Deletion complete')
                    ->body(sprintf('%d deleted; %d not deleted.', $deleted, $failed));

                if ($failed > 0) {
                    $notification->warning();
                } else {
                    $notification->success();
                }

                $notification->send();
            });
    }
}
