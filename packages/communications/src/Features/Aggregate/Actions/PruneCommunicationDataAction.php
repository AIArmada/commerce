<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Aggregate\Actions;

use AIArmada\Communications\Models\Communication;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;

final class PruneCommunicationDataAction
{
    public function __construct(
        private readonly DeleteCommunicationAggregateAction $deleteCommunication,
    ) {}

    public function handle(DateTimeInterface $before): int
    {
        $pruned = 0;

        $pruned += (new Communication)->getConnection()->transaction(function () use ($before): int {
            $query = Communication::query()
                ->where(function ($q) use ($before): void {
                    $q->where('completed_at', '<', $before)
                        ->orWhere('failed_at', '<', $before)
                        ->orWhere('cancelled_at', '<', $before)
                        ->orWhere('expires_at', '<', $before);
                });

            $count = 0;
            $query->chunkById(100, function (Collection $communications) use (&$count): void {
                foreach ($communications as $communication) {
                    $this->deleteCommunication->handle((string) $communication->getKey());
                    $count++;
                }
            });

            return $count;
        });

        return $pruned;
    }
}
