<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocTemplateResource\Pages;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentDocs\Resources\DocTemplateResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateDocTemplate extends CreateRecord
{
    protected static string $resource = DocTemplateResource::class;

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
