<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources\SegmentResource\Pages;

use AIArmada\FilamentCustomers\Resources\SegmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSegment extends CreateRecord
{
    protected static string $resource = SegmentResource::class;
}
