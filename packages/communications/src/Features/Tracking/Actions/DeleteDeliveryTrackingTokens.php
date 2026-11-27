<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Tracking\Actions;

use AIArmada\Communications\Features\Tracking\Api\DeliveryTrackingCleaner;
use AIArmada\Communications\Features\Tracking\Models\CommunicationTrackingToken;
use RuntimeException;

final readonly class DeleteDeliveryTrackingTokens implements DeliveryTrackingCleaner
{
    public function cleanup(string $deliveryId): void
    {
        CommunicationTrackingToken::query()->where('delivery_id', $deliveryId)
            ->eachById(static function (CommunicationTrackingToken $token): void {
                if (! $token->delete()) {
                    throw new RuntimeException('Communication dependent deletion was cancelled.');
                }
            }, 200);
    }
}
