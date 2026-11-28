<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Versioning\Queries;

use AIArmada\Docs\Features\Versioning\Models\DocVersion;
use AIArmada\Docs\Runtime\VersionRelationModel;

final readonly class VersionRelationModelResolver implements VersionRelationModel
{
    public function version(): string
    {
        return DocVersion::class;
    }
}
