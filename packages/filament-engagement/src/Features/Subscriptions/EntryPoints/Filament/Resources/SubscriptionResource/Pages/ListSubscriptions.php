<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Features\Subscriptions\EntryPoints\Filament\Resources\SubscriptionResource\Pages;

use AIArmada\FilamentEngagement\Features\Subscriptions\EntryPoints\Filament\Resources\SubscriptionResource;
use Filament\Resources\Pages\ListRecords;

final class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;
}
