<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions\Exemption;

use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Queries\TaxOwnerScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final readonly class UpdateTaxExemptionAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(int | string $id, array $attributes): TaxExemption
    {
        return DB::transaction(function () use ($id, $attributes): TaxExemption {
            $exemption = TaxOwnerScope::lockWritable(TaxExemption::query(), [$id])->firstOrFail();
            $exemption->fill(Arr::only($attributes, [
                'exemptable_id',
                'exemptable_type',
                'tax_zone_id',
                'reason',
                'certificate_number',
                'document_path',
                'starts_at',
                'expires_at',
            ]));

            if (! $exemption->isPending() && $exemption->isDirty()) {
                throw new AuthorizationException('Only pending exemptions can be edited. Use the authorized lifecycle actions.');
            }

            $exemption->save();

            return $exemption;
        });
    }
}
