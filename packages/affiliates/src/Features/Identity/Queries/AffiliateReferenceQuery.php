<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Queries;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccountSnapshot;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateReferenceReader;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Support\LikeSearch;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final readonly class AffiliateReferenceQuery implements AffiliateReferenceReader
{
    public function exists(string $id): bool
    {
        if (! Str::isUuid($id)) {
            return false;
        }

        if (! config('affiliates.owner.enabled', false)) {
            return Affiliate::query()->whereKey($id)->exists();
        }

        try {
            OwnerWriteGuard::findOrFailForOwner(
                Affiliate::class,
                $id,
                includeGlobal: (bool) config('affiliates.owner.include_global', false),
            );

            return true;
        } catch (AuthorizationException | InvalidArgumentException | RuntimeException) {
            return false;
        }
    }

    public function forOwner(string $ownerType, string $ownerId): ?AffiliateAccountSnapshot
    {
        $affiliate = Affiliate::query()
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->orderBy('id')
            ->first();

        if ($affiliate === null) {
            return null;
        }

        return new AffiliateAccountSnapshot(
            id: (string) $affiliate->getKey(),
            code: $affiliate->code,
            status: $affiliate->status->label(),
            commissionType: $affiliate->commission_type->value,
            commissionRate: $affiliate->commission_rate,
            currency: $affiliate->currency,
        );
    }

    /** @return array<string, string> */
    public function options(string $search = '', int $limit = 50): array
    {
        $query = Affiliate::query()->forOwner(includeGlobal: (bool) config('affiliates.owner.include_global', false));
        $search = mb_trim($search);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                LikeSearch::whereLike($builder, 'name', LikeSearch::contains($search));
                LikeSearch::orWhereLike($builder, 'code', LikeSearch::contains($search));
            });
        }

        /** @var array<string, string> $options */
        $options = $query->orderBy('name')->orderBy('id')->limit(max(1, min(100, $limit)))->pluck('name', 'id')->all();

        return $options;
    }

    public function label(string $id): ?string
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $name = Affiliate::query()->forOwner(includeGlobal: (bool) config('affiliates.owner.include_global', false))->whereKey($id)->value('name');

        return is_string($name) ? $name : null;
    }
}
