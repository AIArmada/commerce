<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Events\SuppressionLifted;
use AIArmada\Communications\Models\CommunicationSuppression;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use RuntimeException;

final class LiftSuppressionAction
{
    public function handle(string $suppressionId): CommunicationSuppression
    {
        $suppression = CommunicationSuppression::query()->findOrFail($suppressionId);

        if ($suppression->lifted_at !== null) {
            throw new RuntimeException("Suppression {$suppressionId} is already lifted.");
        }

        $suppression->lifted_at = CarbonImmutable::now();
        $suppression->save();

        Event::dispatch(new SuppressionLifted(
            suppressionId: $suppression->id,
            reason: $suppression->reason->value,
            destinationHash: $suppression->destination_hash ?? '',
        ));

        return $suppression;
    }
}
