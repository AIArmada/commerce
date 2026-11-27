<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Tracking\Actions;

use AIArmada\Communications\Features\Tracking\Api\DeliveryTrackingCleaner;
use AIArmada\Communications\Features\Tracking\Models\CommunicationTrackingToken;

final readonly class DeleteDeliveryTrackingTokens implements DeliveryTrackingCleaner
{
    public function cleanup(string $deliveryId): void
    {
        CommunicationTrackingToken::query()->where('delivery_id', $deliveryId)
            ->eachById(fn (CommunicationTrackingToken $token) => $token->delete(), 200);
    }
}
