<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\Queries;

use AIArmada\Customers\Features\Segmentation\Models\CustomerGroup;
use AIArmada\Customers\Features\Segmentation\Models\Segment;
use AIArmada\Customers\Runtime\SegmentationRelationModels;

final readonly class SegmentationRelationModelsResolver implements SegmentationRelationModels
{
    public function segment(): string
    {
        return Segment::class;
    }

    public function group(): string
    {
        return CustomerGroup::class;
    }
}
