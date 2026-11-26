<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Queries;

use AIArmada\Affiliates\Features\Identity\Api\PortalActorAffiliateReader;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\Features\Identity\Models\AffiliateClaim;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final readonly class PortalActorAffiliateQuery implements PortalActorAffiliateReader
{
    public function affiliateIdForActor(string $actorType, string $actorId): ?string
    {
        if ($actorType === '' || $actorId === '') {
            return null;
        }

        $matches = Affiliate::query()
            ->forOwner()
            ->where(static function (Builder $query) use ($actorType, $actorId): void {
                $query->where(static function (Builder $owned) use ($actorType, $actorId): void {
                    $owned->where('owner_type', $actorType)->where('owner_id', $actorId);
                });

                $query->orWhereIn('id', AffiliateClaim::query()
                    ->forOwner()
                    ->where('claimant_type', $actorType)
                    ->where('claimant_id', $actorId)
                    ->where('status', 'active')
                    ->whereNull('revoked_at')
                    ->select('affiliate_id'));

                $query->orWhere(static function (Builder $registered) use ($actorType, $actorId): void {
                    $registered->where('registration_actor_type', $actorType)
                        ->where('registration_actor_id', $actorId);
                });
            })
            ->limit(2)
            ->pluck('id');

        if ($matches->count() !== 1) {
            return null;
        }

        $id = $matches->first();

        return is_string($id) && Str::isUuid($id) ? $id : null;
    }
}
