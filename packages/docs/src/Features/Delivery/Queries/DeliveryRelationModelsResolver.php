<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Delivery\Queries;

use AIArmada\Docs\Features\Delivery\Models\DocEmail;
use AIArmada\Docs\Features\Delivery\Models\DocShareLink;
use AIArmada\Docs\Runtime\DeliveryRelationModels;

final readonly class DeliveryRelationModelsResolver implements DeliveryRelationModels
{
    public function email(): string
    {
        return DocEmail::class;
    }

    public function shareLink(): string
    {
        return DocShareLink::class;
    }
}
