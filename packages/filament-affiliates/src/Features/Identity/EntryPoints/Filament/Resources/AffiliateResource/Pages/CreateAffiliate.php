<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Identity\EntryPoints\Filament\Resources\AffiliateResource\Pages;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateAdministration;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\FilamentAffiliates\Features\Identity\EntryPoints\Filament\Resources\AffiliateResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class CreateAffiliate extends CreateRecord
{
    protected static string $resource = AffiliateResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $id = app(AffiliateAdministration::class)->create($data);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'data.linked_user' => $exception->getMessage(),
            ]);
        }

        return Affiliate::query()->findOrFail($id);
    }
}
