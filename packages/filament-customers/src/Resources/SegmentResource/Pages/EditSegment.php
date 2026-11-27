<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources\SegmentResource\Pages;

use AIArmada\Customers\Features\Segmentation\Actions\SaveSegment;
use AIArmada\Customers\Features\Segmentation\Models\Segment;
use AIArmada\FilamentCustomers\Resources\SegmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class EditSegment extends EditRecord
{
    protected static string $resource = SegmentResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Segment) {
            throw new InvalidArgumentException('Invalid segment record.');
        }

        return app(SaveSegment::class)->update($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->using(fn (Segment $record): bool => app(SaveSegment::class)->delete($record)),
        ];
    }
}
