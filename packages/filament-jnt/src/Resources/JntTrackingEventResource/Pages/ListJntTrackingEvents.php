<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Resources\JntTrackingEventResource\Pages;

use AIArmada\FilamentJnt\Resources\JntTrackingEventResource;
use AIArmada\FilamentJnt\Resources\Pages\ReadOnlyListRecords;
use Override;

final class ListJntTrackingEvents extends ReadOnlyListRecords
{
    protected static string $resource = JntTrackingEventResource::class;

    #[Override]
    public function getTitle(): string
    {
        return 'J&T Tracking Events';
    }

    #[Override]
    public function getSubheading(): string
    {
        return 'View tracking status updates from J&T Express.';
    }
}
