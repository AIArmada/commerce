<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxExemptionResource\Pages;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\FilamentTax\Resources\TaxExemptionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTaxExemption extends EditRecord
{
    protected static string $resource = TaxExemptionResource::class;

    protected function beforeSave(): void
    {
        $this->validateExemptableEntity();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Revalidate that the exemptable entity belongs to the current owner.
     * UI-level scoping is not sufficient; server-side revalidation is mandatory.
     */
    private function validateExemptableEntity(): void
    {
        $exemptableType = $this->data['exemptable_type'] ?? null;
        $exemptableId = $this->data['exemptable_id'] ?? null;

        if (! $exemptableType || ! $exemptableId) {
            return;
        }

        if (! class_exists($exemptableType) || ! is_a($exemptableType, Model::class, true)) {
            return;
        }

        // If the exemptable model has owner scoping, validate it belongs to the current owner
        if (in_array(HasOwner::class, class_uses_recursive($exemptableType), true)) {
            OwnerWriteGuard::findOrFailForOwner($exemptableType, $exemptableId);
        }
    }
}
