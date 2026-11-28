<?php

declare(strict_types=1);

namespace AIArmada\Docs\Runtime;

/** @phpstan-type RelatedDocPayment \AIArmada\Docs\Features\Payments\Models\DocPayment */
interface PaymentRelationModel
{
    /** @return class-string<RelatedDocPayment> */
    public function payment(): string;
}
