<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A merchant conversion was reversed or voided for the first time.
 */
final class AffiliateConversionReversed
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly ?string $origin,
        public readonly ?string $sourceRef,
        public readonly ?string $externalReference,
        public readonly string $reason,
    ) {}
}
