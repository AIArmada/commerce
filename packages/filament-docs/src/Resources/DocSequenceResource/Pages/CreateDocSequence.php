<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocSequenceResource\Pages;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentDocs\Resources\DocSequenceResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateDocSequence extends CreateRecord
{
    protected static string $resource = DocSequenceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! config('docs.owner.enabled', false)) {
            return $data;
        }

        $owner = OwnerContext::resolve();

        if ($owner === null) {
            return $data;
        }

        $data['owner_type'] = $owner->getMorphClass();
        $data['owner_id'] = $owner->getKey();

        return $data;
    }
}
