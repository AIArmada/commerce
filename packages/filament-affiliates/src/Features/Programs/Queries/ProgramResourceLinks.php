<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Programs\Queries;

use AIArmada\FilamentAffiliates\Features\Programs\Api\ProgramAdminLinks;
use AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateProgramResource;

final readonly class ProgramResourceLinks implements ProgramAdminLinks
{
    public function view(string $programId): string
    {
        return AffiliateProgramResource::getUrl('view', ['record' => $programId]);
    }
}
