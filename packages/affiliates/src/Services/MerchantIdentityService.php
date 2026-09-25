<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Contracts\MerchantIdentity;
use AIArmada\Affiliates\Data\MerchantAffiliate;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\CommerceSupport\Support\OwnerScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Merchant affiliate recognition.
 *
 * Merchant rows resolve by id; verified contact email maps to Active
 * affiliates. Unknown ids resolve to null — recognition never gates.
 */
final class MerchantIdentityService implements MerchantIdentity
{
    public function find(string $id): ?MerchantAffiliate
    {
        $affiliate = Affiliate::query()->withoutOwnerScope()->whereKey($id)->first();

        return $affiliate instanceof Affiliate ? self::toMerchantAffiliate($affiliate) : null;
    }

    public function findIdForEmail(string $email): ?string
    {
        // contact_email is a virtual attribute stored in contact_methods,
        // not a DB column — match on the email contact method instead.
        $affiliate = Affiliate::query()
            ->withoutOwnerScope()
            ->whereHas('contactMethods', function (Builder $query) use ($email): void {
                $query->withoutGlobalScope(OwnerScope::class)
                    ->where('type', 'email')
                    ->where('purpose', 'general')
                    ->where(fn (Builder $q) => $q->where('value', $email)
                        ->orWhere('normalized_value', $email));
            })
            ->whereState('status', Active::class)
            ->first();

        return $affiliate instanceof Affiliate ? (string) $affiliate->getKey() : null;
    }

    private static function toMerchantAffiliate(Affiliate $affiliate): MerchantAffiliate
    {
        /** @var string|null $ownerType */
        $ownerType = $affiliate->owner_type;
        /** @var string|int|null $ownerId */
        $ownerId = $affiliate->owner_id;

        return new MerchantAffiliate(
            id: (string) $affiliate->getKey(),
            code: (string) $affiliate->code,
            email: $affiliate->contact_email,
            ownerType: $ownerType,
            ownerId: $ownerId,
        );
    }
}
