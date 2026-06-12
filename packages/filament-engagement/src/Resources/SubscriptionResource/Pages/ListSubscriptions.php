<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Resources\SubscriptionResource\Pages;

use AIArmada\FilamentEngagement\Resources\SubscriptionResource;
use Filament\Resources\Pages\ListRecords;

final class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;
}
