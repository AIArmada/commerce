<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Network;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Data\NetworkAffiliate;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Resolve network affiliate identity from core affiliates.
 */
final class AffiliatesIdentityResolver implements AffiliateIdentityResolver
{
    public function find(string $affiliateId): ?NetworkAffiliate
    {
        $affiliate = Affiliate::query()->withoutOwnerScope()->whereKey($affiliateId)->first();

        return $affiliate instanceof Affiliate ? self::toNetworkAffiliate($affiliate) : null;
    }

    public function findAccessible(string $affiliateId): ?NetworkAffiliate
    {
        if (! config('affiliates.owner.enabled', false)) {
            return $this->find($affiliateId);
        }

        try {
            $affiliate = OwnerWriteGuard::findOrFailForOwner(
                Affiliate::class,
                $affiliateId,
                includeGlobal: false,
                message: 'Affiliate is not accessible in the current owner scope.',
            );
        } catch (ModelNotFoundException | AuthorizationException) {
            return null;
        }

        return $affiliate instanceof Affiliate ? self::toNetworkAffiliate($affiliate) : null;
    }

    public function findIdForVerifiedEmail(string $email): ?string
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

    private static function toNetworkAffiliate(Affiliate $affiliate): NetworkAffiliate
    {
        /** @var string|null $ownerType */
        $ownerType = $affiliate->owner_type;
        /** @var string|int|null $ownerId */
        $ownerId = $affiliate->owner_id;

        return new NetworkAffiliate(
            id: (string) $affiliate->getKey(),
            code: (string) $affiliate->code,
            email: $affiliate->contact_email,
            ownerType: $ownerType,
            ownerId: $ownerId,
        );
    }
}
