<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

use Illuminate\Database\Eloquent\Model;

interface AffiliateOwnerResolver
{
    public function resolveCurrentOwner(): ?Model;
}
