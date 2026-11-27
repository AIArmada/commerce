<?php

declare(strict_types=1);

namespace AIArmada\Customers\Runtime;

use AIArmada\Customers\Features\Segmentation\Models\CustomerGroup;
use AIArmada\Customers\Features\Segmentation\Models\Segment;

/**
 * Internal Eloquent relationship composition, not a cross-feature domain API.
 * The owning feature supplies the related model classes.
 */
interface SegmentationRelationModels
{
    /** @return class-string<Segment> */
    public function segment(): string;

    /** @return class-string<CustomerGroup> */
    public function group(): string;
}
