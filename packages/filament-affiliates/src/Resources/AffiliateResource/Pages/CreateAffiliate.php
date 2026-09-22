<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateResource\Pages;

use AIArmada\FilamentAffiliates\Actions\ResolveAffiliateLinkedOwner;
use AIArmada\FilamentAffiliates\Actions\ValidateAffiliateParentAssignment;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class CreateAffiliate extends CreateRecord
{
    protected static string $resource = AffiliateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = parent::mutateFormDataBeforeCreate($data);
        $data = ValidateAffiliateParentAssignment::run($data);

        return ResolveAffiliateLinkedOwner::run($data);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $ownerType = $data['owner_type'] ?? null;
        $ownerId = $data['owner_id'] ?? null;

        unset($data['owner_type'], $data['owner_id']);

        $record = new (static::getModel())($data);

        if (is_string($ownerType) && $ownerType !== '' && $ownerId !== null && $ownerId !== '') {
            $record->forceFill([
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
            ]);
        }

        try {
            $record->save();
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'data.linked_user' => $exception->getMessage(),
            ]);
        }

        return $record;
    }
}
