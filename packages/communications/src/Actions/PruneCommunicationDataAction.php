<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Models\Communication;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

final class PruneCommunicationDataAction
{
    public function handle(DateTimeInterface $before): int
    {
        $pruned = 0;

        $pruned += DB::transaction(function () use ($before) {
            $query = Communication::query()
                ->where(function ($q) use ($before) {
                    $q->where('completed_at', '<', $before)
                        ->orWhere('failed_at', '<', $before)
                        ->orWhere('cancelled_at', '<', $before)
                        ->orWhere('expires_at', '<', $before);
                });

            $count = $query->count();
            $query->each(function (Communication $communication) {
                $communication->delete();
            });

            return $count;
        });

        return $pruned;
    }
}
