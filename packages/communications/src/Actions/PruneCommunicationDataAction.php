<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Models\Communication;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class PruneCommunicationDataAction
{
    public function __construct(
        private readonly DeleteCommunicationAggregateAction $deleteCommunication,
    ) {}

    public function handle(DateTimeInterface $before): int
    {
        $pruned = 0;

        $pruned += DB::transaction(function () use ($before) {
            $query = Communication::query()
                ->where(function ($q) use ($before): void {
                    $q->where('completed_at', '<', $before)
                        ->orWhere('failed_at', '<', $before)
                        ->orWhere('cancelled_at', '<', $before)
                        ->orWhere('expires_at', '<', $before);
                });

            $count = $query->count();
            $query->chunkById(100, function (Collection $communications): void {
                $communications->each(function (Communication $communication): void {
                    $this->deleteCommunication->handle((string) $communication->getKey());
                });
            });

            return $count;
        });

        return $pruned;
    }
}
