<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\GiftCardResource\Pages;

use AIArmada\FilamentVouchers\Resources\GiftCardResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateGiftCard extends CreateRecord
{
    protected static string $resource = GiftCardResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['current_balance']) && ! empty($data['initial_balance'])) {
            $data['current_balance'] = $data['initial_balance'];
        }

        return $data;
    }
}
