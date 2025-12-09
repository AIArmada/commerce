<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\GiftCardResource\Pages;

use AIArmada\FilamentVouchers\Actions\BulkIssueGiftCardsAction;
use AIArmada\FilamentVouchers\Resources\GiftCardResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListGiftCards extends ListRecords
{
    protected static string $resource = GiftCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            BulkIssueGiftCardsAction::make(),
        ];
    }
}
