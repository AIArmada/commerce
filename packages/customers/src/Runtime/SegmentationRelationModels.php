<?php

declare(strict_types=1);

namespace AIArmada\Customers\Runtime;

/**
 * Internal Eloquent relationship composition, not a cross-feature domain API.
 * The owning feature supplies the related model classes.
 */
interface SegmentationRelationModels
{
    /** @return class-string<\AIArmada\Customers\Features\Segmentation\Models\Segment> */
    public function segment(): string;

    /** @return class-string<\AIArmada\Customers\Features\Segmentation\Models\CustomerGroup> */
    public function group(): string;
}
