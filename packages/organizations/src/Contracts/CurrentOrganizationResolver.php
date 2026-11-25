<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Contracts;

use AIArmada\Organizations\Models\Organization;

interface CurrentOrganizationResolver
{
    public function resolve(): ?Organization;
}
