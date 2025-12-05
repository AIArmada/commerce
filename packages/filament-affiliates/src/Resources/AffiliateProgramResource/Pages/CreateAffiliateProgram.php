<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateAffiliateProgram extends CreateRecord
{
    protected static string $resource = AffiliateProgramResource::class;
}
