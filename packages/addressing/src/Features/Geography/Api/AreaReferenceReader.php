<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Features\Geography\Api;

interface AreaReferenceReader
{
    /**
     * @param  list<string>  $areaIds
     * @return list<array{area_id: string, name: string, name_type: string, is_preferred: bool}>
     */
    public function aliases(array $areaIds, ?string $source = null): array;

    /**
     * @param  list<string>  $childIds
     * @return list<array{parent_id: string, child_id: string, hierarchy_type: ?string}>
     */
    public function activeContainmentParents(array $childIds): array;
}
