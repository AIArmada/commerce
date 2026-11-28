<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Api;

interface MovementReferences
{
    /**
     * @param  list<string>  $references
     * @return list<array{reference: string, owner_type: string|null, owner_id: string|null}>
     */
    public function matching(array $references): array;
}
