<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Contracts;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Organizations\Models\Organization;

interface CurrentOrganizationResolver extends OwnerResolverInterface
{
    public function resolve(): ?Organization;
}
