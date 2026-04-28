<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateResource\Pages;

use AIArmada\FilamentAffiliates\Actions\ValidateAffiliateParentAssignment;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateAffiliate extends CreateRecord
{
    protected static string $resource = AffiliateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = parent::mutateFormDataBeforeCreate($data);

        return ValidateAffiliateParentAssignment::run($data);
    }
}
