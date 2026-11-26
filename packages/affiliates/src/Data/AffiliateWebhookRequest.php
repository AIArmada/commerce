<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

final readonly class AffiliateWebhookRequest
{
    /** @param array<string, string> $headers */
    public function __construct(public string $endpoint, public array $headers, public string $body, public ?string $signature) {}
}
