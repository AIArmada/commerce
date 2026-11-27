<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources\SegmentResource\Pages;

use AIArmada\Customers\Features\Segmentation\Actions\SaveSegment;
use AIArmada\FilamentCustomers\Resources\SegmentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSegment extends CreateRecord
{
    protected static string $resource = SegmentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveSegment::class)->create($data);
    }

}
