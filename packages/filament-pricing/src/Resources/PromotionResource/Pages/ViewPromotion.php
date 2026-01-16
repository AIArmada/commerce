<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Resources\PromotionResource\Pages;

use AIArmada\FilamentPricing\Resources\PromotionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

final class ViewPromotion extends ViewRecord
{
    protected static string $resource = PromotionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
