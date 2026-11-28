<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Features\SocialProfiles\Api;

use AIArmada\Contacting\Data\ContactSnapshotSourceData;

interface ReadsSocialProfileSnapshotSources
{
    /** @param iterable<string> $ids
     * @return list<ContactSnapshotSourceData>
     */
    public function read(iterable $ids): array;
}
