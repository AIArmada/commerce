<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Queries;

use AIArmada\Affiliates\Contracts\MerchantIdentity;
use AIArmada\Affiliates\Data\MerchantAffiliate;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\CommerceSupport\Data\OwnerTableReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Merchant affiliate recognition.
 *
 * Merchant rows resolve by id; verified general contact email maps to
 * Active affiliates, preferring the current owner when one is resolved.
 * Unknown ids resolve to null — recognition never gates.
 */
final class MerchantAffiliateQuery implements MerchantIdentity
{
    public function ownerTable(): OwnerTableReference
    {
        $affiliate = new Affiliate;

        return new OwnerTableReference($affiliate->getTable(), $affiliate->getKeyName(), spl_object_id($affiliate->getConnection()));
    }

    public function find(string $id): ?MerchantAffiliate
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $affiliate = Affiliate::query()->withoutOwnerScope()->whereKey($id)->first();

        return $affiliate instanceof Affiliate ? self::toMerchantAffiliate($affiliate) : null;
    }

    public function findAccessible(string $id): ?MerchantAffiliate
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        if (! config('affiliates.owner.enabled', false)) {
            return $this->find($id);
        }

        try {
            $affiliate = OwnerWriteGuard::findOrFailForOwner(
                Affiliate::class,
                $id,
                includeGlobal: false,
            );
        } catch (AuthorizationException) {
            return null;
        }

        return self::toMerchantAffiliate($affiliate);
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
            $owned = (clone $query)->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey())->limit(2)->pluck('id');

            if ($owned->isNotEmpty()) {
                return $owned->count() === 1 ? (string) $owned->first() : null;
            }
        }

        $matches = $query->limit(2)->pluck('id');

        return $matches->count() === 1 ? (string) $matches->first() : null;
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
