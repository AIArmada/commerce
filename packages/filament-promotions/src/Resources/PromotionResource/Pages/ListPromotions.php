<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions\Resources\PromotionResource\Pages;

use AIArmada\FilamentPromotions\Resources\PromotionResource;
use Filament\Resources\Pages\ListRecords;

final class ListPromotions extends ListRecords
{
    protected static string $resource = PromotionResource::class;
}
