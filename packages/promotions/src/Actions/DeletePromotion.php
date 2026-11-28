<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Promotions\Models\Promotion;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

final readonly class DeletePromotion
{
    public function handle(Promotion $promotion): bool
    {
        return $promotion->getConnection()->transaction(function () use ($promotion): bool {
            $locked = config('promotions.features.owner.enabled', false)
                ? OwnerWriteGuard::findOrFailForOwner(Promotion::class, $promotion->getKey(), lockForUpdate: true)
                : Promotion::query()->whereKey($promotion->getKey())->lockForUpdate()->first();

            return $locked instanceof Promotion && $locked->delete() === true;
        });
    }

    /** @param Collection<int, Promotion> $records */
    public function handleMany(Collection $records): void
    {
        if ($records->isEmpty()) {
            return;
        }

        (new Promotion)->getConnection()->transaction(function () use ($records): void {
            foreach ($records->sortBy(static fn (Promotion $record): string => (string) $record->getKey()) as $record) {
                if (! $this->handle($record)) {
                    throw new RuntimeException('Unable to delete promotion in the selected batch.');
                }
            }
        });
    }
}
