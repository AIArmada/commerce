<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\GiftCardResource\Pages;

use AIArmada\FilamentVouchers\Resources\GiftCardResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditGiftCard extends EditRecord
{
    protected static string $resource = GiftCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
