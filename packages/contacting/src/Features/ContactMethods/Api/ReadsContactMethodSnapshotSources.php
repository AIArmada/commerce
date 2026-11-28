<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Features\ContactMethods\Api;

use AIArmada\Contacting\Data\ContactSnapshotSourceData;

interface ReadsContactMethodSnapshotSources
{
    /** @param iterable<string> $ids
     * @return list<ContactSnapshotSourceData>
     */
    public function read(iterable $ids): array;
}
