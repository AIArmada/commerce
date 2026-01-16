<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Resources\PromotionResource\Pages;

use AIArmada\FilamentPricing\Resources\PromotionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListPromotions extends ListRecords
{
    protected static string $resource = PromotionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
