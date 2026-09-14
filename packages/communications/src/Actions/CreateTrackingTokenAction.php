<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Contracts\DestinationProtector;
use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Data\CreatedTrackingTokenData;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationTrackingToken;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class CreateTrackingTokenAction
{
    public function __construct(
        private readonly PayloadRedactor $redactor,
        private readonly DestinationProtector $protector,
    ) {}

    public function handle(
        string $deliveryId,
        string $kind,
        ?string $targetUrl = null,
        ?string $expiresAt = null,
        ?array $metadata = null,
    ): CreatedTrackingTokenData {
        $delivery = CommunicationDelivery::query()->findOrFail($deliveryId);

        if (CommunicationDelivery::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(CommunicationDelivery::class, $deliveryId);
        }

        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);

        $trackingToken = new CommunicationTrackingToken;
        $trackingToken->delivery_id = $delivery->id;
        $trackingToken->kind = $kind;
        $trackingToken->token_hash = $tokenHash;

        if ($targetUrl !== null) {
            $trackingToken->target_url_ciphertext = $this->protector->encrypt(
                $this->validatedTargetUrl($targetUrl),
            );
            $trackingToken->target_host = (string) parse_url($targetUrl, PHP_URL_HOST);
        }

        $trackingToken->expires_at = $expiresAt !== null ? $this->parseExpiresAt($expiresAt) : null;
        $trackingToken->metadata = $metadata !== null ? $this->redactor->redact($metadata) : null;
        $trackingToken->save();

        return new CreatedTrackingTokenData($trackingToken, $token);
    }

    private function validatedTargetUrl(string $targetUrl): string
    {
        $parts = parse_url($targetUrl);

        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || ! is_string($parts['host']) || $parts['host'] === '') {
            throw new InvalidArgumentException('Tracking target URL must be an absolute URL with a host.');
        }

        if (! in_array(mb_strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
            throw new InvalidArgumentException('Tracking target URL must use the http or https scheme.');
        }

        $allowedHosts = config('communications.tracking.allowed_hosts', []);

        if (is_array($allowedHosts) && $allowedHosts !== []) {
            $host = mb_strtolower($parts['host']);
            $allowed = array_map(
                static fn (mixed $allowedHost): string => mb_strtolower((string) $allowedHost),
                $allowedHosts,
            );

            if (! in_array($host, $allowed, true)) {
                throw new InvalidArgumentException(
                    "Tracking target URL host [{$parts['host']}] is not in the allowed hosts list.",
                );
            }
        }

        return $targetUrl;
    }

    private function parseExpiresAt(string $expiresAt): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($expiresAt);
        } catch (Throwable) {
            throw new InvalidArgumentException("Invalid tracking token expiry date [{$expiresAt}].");
        }
    }
}
