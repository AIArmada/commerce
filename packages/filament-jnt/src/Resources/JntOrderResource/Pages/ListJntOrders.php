<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Resources\JntOrderResource\Pages;

use AIArmada\CommerceSupport\Filament\Pages\ReadOnlyListRecords;
use AIArmada\FilamentJnt\Resources\JntOrderResource;
use Override;

final class ListJntOrders extends ReadOnlyListRecords
{
    protected static string $resource = JntOrderResource::class;

    #[Override]
    public function getTitle(): string
    {
        return 'J&T Express Orders';
    }

    #[Override]
    public function getSubheading(): string
    {
        return 'Manage and track J&T Express shipping orders.';
    }
}
