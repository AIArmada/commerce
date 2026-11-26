<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Contracts;

use AIArmada\Addressing\Models\AddressArea;
use Illuminate\Database\Eloquent\Collection;

interface AddressAreaSearch
{
    /** @return Collection<int, AddressArea> */
    public function execute(string $query, ?string $countryCode = null, ?string $role = null, ?string $type = null, ?string $parentId = null, ?string $hierarchyType = null, ?string $postalCode = null, int $limit = 25): Collection;
}
