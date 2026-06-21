<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Events\CommunicationCancelled;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

final class CancelCommunicationAction
{
    public function handle(string $communicationId, ?string $reason = null): Communication
    {
        $communication = Communication::query()->findOrFail($communicationId);

        $communication->status = CommunicationStatus::Cancelled;
        $communication->cancelled_at = CarbonImmutable::now();
        $communication->save();

        CommunicationDelivery::query()
            ->where('communication_id', $communication->id)
            ->whereIn('status', ['pending', 'scheduled', 'queued', 'sending'])
            ->update([
                'status' => DeliveryStatus::Cancelled,
                'cancelled_at' => CarbonImmutable::now(),
            ]);

        Event::dispatch(new CommunicationCancelled(
            communicationId: $communication->id,
            reason: $reason,
        ));

        return $communication;
    }
}
