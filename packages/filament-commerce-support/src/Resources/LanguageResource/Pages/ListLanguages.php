<?php

declare(strict_types=1);

namespace AIArmada\FilamentCommerceSupport\Resources\LanguageResource\Pages;

use AIArmada\FilamentCommerceSupport\Resources\LanguageResource;
use Filament\Resources\Pages\ListRecords;

final class ListLanguages extends ListRecords
{
    protected static string $resource = LanguageResource::class;
}
