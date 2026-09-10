<?php

declare(strict_types=1);

namespace AIArmada\Moderation\Actions;

use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\Moderation\Enums\BlockStatus;
use AIArmada\Moderation\Models\Block;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class ExpireModerationBlocksAction
{
    public function execute(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();

        $runner = new OwnerBatchRunner(Block::class, [
            'enabled' => 'moderation.owner.enabled',
            'include_global' => 'moderation.owner.include_global',
        ]);

        return (int) $runner->run(fn (): int => $this->expireForCurrentOwner($now));
    }

    private function expireForCurrentOwner(CarbonImmutable $now): int
    {
        $expired = 0;

        Block::query()
            ->where('status', BlockStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->chunkById(100, function (Collection $blocks) use (&$expired): void {
                DB::transaction(function () use ($blocks, &$expired): void {
                    foreach ($blocks as $block) {
                        if (! $block instanceof Block) {
                            continue;
                        }

                        $block->expire()->save();
                        $expired++;
                    }
                });
            });

        return $expired;
    }
}
