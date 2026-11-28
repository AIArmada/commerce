<?php

declare(strict_types=1);

namespace AIArmada\Customers\Runtime;

/**
 * Internal Eloquent relationship composition, not a cross-feature domain API.
 * The owning feature supplies the related model classes.
 *
 * @phpstan-type RelatedCustomerGroup \AIArmada\Customers\Features\Segmentation\Models\CustomerGroup
 * @phpstan-type RelatedSegment \AIArmada\Customers\Features\Segmentation\Models\Segment
 */
interface SegmentationRelationModels
{
    /** @return class-string<RelatedSegment> */
    public function segment(): string;

    /** @return class-string<RelatedCustomerGroup> */
    public function group(): string;
}
