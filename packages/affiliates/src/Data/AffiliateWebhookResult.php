<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

final readonly class AffiliateWebhookResult
{
    public function __construct(public bool $successful, public ?int $status, public ?string $errorCode = null) {}
}
