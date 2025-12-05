<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\GiftCardResource\Pages;

use AIArmada\FilamentVouchers\Resources\GiftCardResource;
use Filament\Resources\Pages\ListRecords;

final class ListGiftCards extends ListRecords
{
    protected static string $resource = GiftCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
            \AIArmada\FilamentVouchers\Actions\BulkIssueGiftCardsAction::make(),
        ];
    }
}
