<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\TitleIssuerResource\Pages;

use AIArmada\FilamentPersons\Resources\TitleIssuerResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateTitleIssuer extends CreateRecord
{
    protected static string $resource = TitleIssuerResource::class;
}
