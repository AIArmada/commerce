<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Actions;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\FilamentTax\Resources\TaxExemptionResource\Schemas\TaxExemptionForm;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

final readonly class ValidateExemptableEntityAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): void
    {
        $type = $data['exemptable_type'] ?? null;
        $id = $data['exemptable_id'] ?? null;

        if (! is_string($type) || ! array_key_exists($type, TaxExemptionForm::getExemptableTypes())
            || ! is_a($type, Model::class, true) || ! is_string($id) || $id === '') {
            throw new AuthorizationException('Invalid tax exemption entity.');
        }

        if (! OwnerUiScope::apply($type::query(), includeGlobal: false)->whereKey($id)->exists()) {
            throw new AuthorizationException('Exemptable entity is not accessible in the current owner scope.');
        }
    }
}
