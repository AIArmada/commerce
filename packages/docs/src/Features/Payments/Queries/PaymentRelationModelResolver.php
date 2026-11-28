<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Payments\Queries;

use AIArmada\Docs\Features\Payments\Models\DocPayment;
use AIArmada\Docs\Runtime\PaymentRelationModel;

final readonly class PaymentRelationModelResolver implements PaymentRelationModel
{
    public function payment(): string
    {
        return DocPayment::class;
    }
}
