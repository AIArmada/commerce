<?php

declare(strict_types=1);

namespace AIArmada\Docs\Runtime;

/** @phpstan-type RelatedDocVersion \AIArmada\Docs\Features\Versioning\Models\DocVersion */
interface VersionRelationModel
{
    /** @return class-string<RelatedDocVersion> */
    public function version(): string;
}
