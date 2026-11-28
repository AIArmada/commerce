<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Actions;

use AIArmada\Communications\Features\Delivery\Api\DeliveryRetention;
use AIArmada\Communications\Features\Delivery\Models\CommunicationAttempt;
use AIArmada\Communications\Features\Delivery\Models\CommunicationEvent;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final readonly class PruneDeliveryData implements DeliveryRetention
{
    public function prune(DateTimeInterface $before, bool $dryRun): array
    {
        $attempts = CommunicationAttempt::query()->where('created_at', '<', $before);
        $events = CommunicationEvent::query()->where('created_at', '<', $before);

        return [
            'attempts' => $dryRun ? $attempts->count() : $this->deleteInChunks($attempts),
            'events' => $dryRun ? $events->count() : $this->deleteInChunks($events),
        ];
    }

    /** @param Builder<covariant Model> $query */
    private function deleteInChunks(Builder $query): int
    {
        $deleted = 0;

        $query->chunkById(100, function (Collection $records) use ($query, &$deleted): void {
            $deleted += (clone $query)->whereKey($records->modelKeys())->delete();
        });

        return $deleted;
    }
}
