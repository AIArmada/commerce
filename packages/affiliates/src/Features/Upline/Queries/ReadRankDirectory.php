<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline\Queries;

use AIArmada\Affiliates\Features\Upline\Api\RankDirectory;
use AIArmada\Affiliates\Features\Upline\Models\AffiliateRank;
use AIArmada\CommerceSupport\Support\LikeSearch;
use Illuminate\Support\Str;

final readonly class ReadRankDirectory implements RankDirectory
{
    public function options(string $search, int $limit = 50): array
    {
        $query = AffiliateRank::query()->forOwner();
        $search = mb_substr(mb_trim($search), 0, 255);

        if ($search !== '') {
            LikeSearch::whereLike($query->getQuery(), 'name', LikeSearch::contains($search));
        }

        /** @var array<string, string> $options */
        $options = $query->orderBy('name')->orderBy('id')->limit(max(1, min(100, $limit)))->pluck('name', 'id')->all();

        return $options;
    }

    public function label(?string $id): ?string
    {
        if ($id === null || ! Str::isUuid($id)) {
            return null;
        }

        $name = AffiliateRank::query()->forOwner()->whereKey($id)->value('name');

        return is_string($name) ? $name : null;
    }

    public function customerDiscount(?string $id): ?array
    {
        if ($id === null || ! Str::isUuid($id)) {
            return null;
        }

        $discount = AffiliateRank::query()->forOwner()->find($id)?->metadata['customer_discount'] ?? null;

        if (! is_array($discount)) {
            return null;
        }

        $type = $discount['type'] ?? 'percentage';
        $value = filter_var($discount['value'] ?? null, FILTER_VALIDATE_INT);

        if (! in_array($type, ['percentage', 'fixed'], true) || $value === false || $value <= 0 || ($type === 'percentage' && $value > 10000)) {
            return null;
        }

        return ['type' => $type, 'value' => $value];
    }
}
