<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\PaymentResource\Pages;

use AIArmada\FilamentChip\Resources\Pages\ReadOnlyListRecords;
use AIArmada\FilamentChip\Resources\PaymentResource;
use Override;

final class ListPayments extends ReadOnlyListRecords
{
    protected static string $resource = PaymentResource::class;

    #[Override]
    public function getTitle(): string
    {
        return 'Payments';
    }

    #[Override]
    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
