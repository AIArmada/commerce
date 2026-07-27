<?php

declare(strict_types=1);

namespace AIArmada\FilamentCommerceSupport\Resources\CurrencyResource\Pages;

use AIArmada\FilamentCommerceSupport\Resources\CurrencyResource;
use Filament\Resources\Pages\ListRecords;

final class ListCurrencies extends ListRecords
{
    protected static string $resource = CurrencyResource::class;
}
