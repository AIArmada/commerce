<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Promotions\Models\Promotion;
use AIArmada\Promotions\Support\PromotionInputValidator;

final readonly class UpdatePromotion
{
    public function __construct(private PromotionInputValidator $validator) {}

    /** @param array<string, mixed> $data */
    public function handle(Promotion $promotion, array $data): Promotion
    {
        return $promotion->getConnection()->transaction(function () use ($promotion, $data): Promotion {
            $locked = config('promotions.features.owner.enabled', false)
                ? OwnerWriteGuard::findOrFailForOwner(Promotion::class, $promotion->getKey(), lockForUpdate: true)
                : Promotion::query()->whereKey($promotion->getKey())->lockForUpdate()->firstOrFail();
            unset($data['owner_type'], $data['owner_id'], $data['usage_count'], $data['deactivated_at']);
            $this->validator->validate(array_replace($locked->getAttributes(), $data), $locked);
            $locked->update($data);

            return $locked->fresh() ?? $locked;
        });
    }
}
