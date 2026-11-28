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

        Communication::query()
            ->where(function ($q) use ($before): void {
                $q->where('completed_at', '<', $before)
                    ->orWhere('failed_at', '<', $before)
                    ->orWhere('cancelled_at', '<', $before)
                    ->orWhere('expires_at', '<', $before);
            })
            ->select('id')
            ->chunkById(100, function (Collection $communications) use (&$pruned, $before): void {
                foreach ($communications as $communication) {
                    if ($this->deleteCommunication->handle((string) $communication->getKey(), $before)) {
                        $pruned++;
                    }
                }
            });

        return $pruned;
    }
}
