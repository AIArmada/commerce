<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocEmailTemplateResource\Pages;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentDocs\Resources\DocEmailTemplateResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateDocEmailTemplate extends CreateRecord
{
    protected static string $resource = DocEmailTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! config('docs.owner.enabled', false)) {
            return $data;
        }

        $owner = app(OwnerResolverInterface::class)->resolve();

        if ($owner === null) {
            return $data;
        }

        $data['owner_type'] = $owner->getMorphClass();
        $data['owner_id'] = $owner->getKey();

        return $data;
    }
}
