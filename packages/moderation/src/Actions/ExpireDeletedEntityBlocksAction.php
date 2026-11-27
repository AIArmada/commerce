<?php

declare(strict_types=1);

namespace AIArmada\Moderation\Actions;

use AIArmada\Moderation\Enums\BlockStatus;
use AIArmada\Moderation\Models\Block;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final readonly class ExpireDeletedEntityBlocksAction
{
    public function execute(Model $entity): void
    {
        (new Block)->getConnection()->transaction(function () use ($entity): void {
            Block::withoutOwnerScope()
                ->where('blockable_type', $entity->getMorphClass())
                ->where('blockable_id', $entity->getKey())
                ->where('status', BlockStatus::Active)
                ->chunkById(500, function (Collection $blocks): void {
                    Block::withoutOwnerScope()
                        ->whereKey($blocks->modelKeys())
                        ->where('status', BlockStatus::Active)
                        ->update([
                            'status' => BlockStatus::Expired->value,
                            'updated_at' => CarbonImmutable::now(),
                        ]);
                });
        });
    }
}
