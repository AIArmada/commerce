<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Resources\AffiliateCommissionTemplateResource\Pages;

use AIArmada\Affiliates\Features\Commissions\Api\CommissionTemplateAdministration;
use AIArmada\Affiliates\Models\AffiliateCommissionTemplate;
use AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Resources\AffiliateCommissionTemplateResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateAffiliateCommissionTemplate extends CreateRecord
{
    protected static string $resource = AffiliateCommissionTemplateResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $id = app(CommissionTemplateAdministration::class)->create($data);

        return AffiliateCommissionTemplate::query()->findOrFail($id);
    }
}
