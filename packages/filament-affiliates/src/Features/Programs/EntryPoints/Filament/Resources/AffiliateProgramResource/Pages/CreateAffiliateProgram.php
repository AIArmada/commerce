<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateProgramResource\Pages;

use AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateProgramResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateAffiliateProgram extends CreateRecord
{
    protected static string $resource = AffiliateProgramResource::class;
}
