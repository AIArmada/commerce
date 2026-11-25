<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Resources\AffiliateCommissionTemplateResource\Pages;

use AIArmada\Affiliates\Features\Commissions\Api\CommissionTemplateAdministration;
use AIArmada\Affiliates\Models\AffiliateCommissionTemplate;
use Illuminate\Database\Eloquent\Model;
use AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Resources\AffiliateCommissionTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditAffiliateCommissionTemplate extends EditRecord
{
    protected static string $resource = AffiliateCommissionTemplateResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(CommissionTemplateAdministration::class)->update((string) $record->getKey(), $data);

        return $record->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
