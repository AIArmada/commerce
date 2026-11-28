<?php

declare(strict_types=1);

namespace AIArmada\Docs\Runtime;

/** @phpstan-type RelatedDocShareLink \AIArmada\Docs\Features\Delivery\Models\DocShareLink */
interface DeliveryRelationModels
{
    /** @return class-string<RelatedDocShareLink> */
    public function shareLink(): string;
}
