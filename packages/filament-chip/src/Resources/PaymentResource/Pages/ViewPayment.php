<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\PaymentResource\Pages;

use AIArmada\FilamentChip\Resources\Pages\ReadOnlyViewRecord;
use AIArmada\FilamentChip\Resources\PaymentResource;
use Override;

final class ViewPayment extends ReadOnlyViewRecord
{
    protected static string $resource = PaymentResource::class;

    #[Override]
    public function getTitle(): string
    {
        return 'Payment Details';
    }
}
