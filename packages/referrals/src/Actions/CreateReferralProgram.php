<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Referrals\Models\ReferralProgram;
use AIArmada\Referrals\Support\ReferralProgramAttributeNormalizer;
use Lorisleiva\Actions\Concerns\AsAction;

final class CreateReferralProgram
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): ReferralProgram
    {
        // Creation and update share one normalizer so the admin edit path can
        // never store a value creation would have refused.
        $normalized = app(ReferralProgramAttributeNormalizer::class)->normalize($attributes);

        if ((bool) config('referrals.owner.enabled', true)) {
            $owner = OwnerContext::resolve();
            OwnerContext::assertResolvedOrExplicitGlobal($owner);
        } else {
            $owner = null;
        }

        $program = new ReferralProgram;
        $program->forceFill($normalized);

        if ($owner !== null) {
            $typeColumn = (string) config('referrals.owner.owner_type_column', 'owner_type');
            $idColumn = (string) config('referrals.owner.owner_id_column', 'owner_id');
            $program->setAttribute($typeColumn, $owner->getMorphClass());
            $program->setAttribute($idColumn, $owner->getKey());
        }

        $program->save();

        return $program;
    }
}
