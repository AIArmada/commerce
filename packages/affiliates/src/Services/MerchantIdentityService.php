<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Contracts\MerchantIdentity;
use AIArmada\Affiliates\Data\MerchantAffiliate;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Merchant affiliate recognition.
 *
 * Merchant rows resolve by id; verified general contact email maps to
 * Active affiliates, preferring the current owner when one is resolved.
 * Unknown ids resolve to null — recognition never gates.
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
        $query = Affiliate::query()
            ->withoutOwnerScope()
            ->whereHas('contactMethods', function (Builder $query) use ($email): void {
                $query->withoutGlobalScope(OwnerScope::class)
                    ->where('type', 'email')
                    ->where('purpose', 'general')
                    ->where('is_verified', true)
                    ->where(fn (Builder $q) => $q->where('value', $email)
                        ->orWhere('normalized_value', $email));
            })
            ->whereState('status', Active::class);

        $owner = OwnerContext::resolve();

        if ($owner !== null) {
            $query->orderByRaw('CASE WHEN owner_type = ? AND owner_id = ? THEN 0 ELSE 1 END', [
                $owner->getMorphClass(),
                (string) $owner->getKey(),
            ]);
        }

        $affiliate = $query->orderBy('id')->first();

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
            handle: $affiliate->handle,
            email: $affiliate->contact_email,
            ownerType: $ownerType,
            ownerId: $ownerId,
        );
    }
}
