<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Features\Catalog\Actions\SyncSiteOffers;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

final class SyncSiteCatalog
{
    public static function make(): Action
    {
        return Action::make('sync_catalog')
            ->label('Sync catalog')
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->requiresConfirmation()
            ->action(function (AffiliateSite $record): array {
                $result = app(SyncSiteOffers::class)->execute($record->toRecord());

                Notification::make()
                    ->title('Catalog sync complete')
                    ->body(sprintf(
                        '%d program(s): %d created, %d updated, %d skipped, %d failed.',
                        $result['programs'],
                        $result['created'],
                        $result['updated'],
                        $result['skipped'],
                        $result['failed'],
                    ))
                    ->success()
                    ->send();

                return $result;
            });
    }
}
