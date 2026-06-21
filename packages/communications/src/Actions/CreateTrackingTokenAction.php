<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationTrackingToken;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class CreateTrackingTokenAction
{
    public function handle(
        string $deliveryId,
        string $kind,
        ?string $targetUrl = null,
        ?string $expiresAt = null,
        ?array $metadata = null,
    ): CommunicationTrackingToken {
        $delivery = CommunicationDelivery::query()->findOrFail($deliveryId);

        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);

        $trackingToken = new CommunicationTrackingToken;
        $trackingToken->delivery_id = $delivery->id;
        $trackingToken->kind = $kind;
        $trackingToken->token_hash = $tokenHash;
        $trackingToken->target_url_ciphertext = $targetUrl;
        $trackingToken->target_host = $targetUrl !== null ? parse_url($targetUrl, PHP_URL_HOST) : null;
        $trackingToken->expires_at = $expiresAt !== null ? CarbonImmutable::parse($expiresAt) : null;
        $trackingToken->metadata = $metadata;
        $trackingToken->save();

        return $trackingToken;
    }

    public function getToken(CommunicationTrackingToken $token): string
    {
        return $token->token_hash;
    }
}
