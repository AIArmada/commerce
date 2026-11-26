<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Programs\Api;

interface ProgramAdminLinks
{
    public function view(string $programId): string;
}
