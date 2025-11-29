<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Events;

use AIArmada\Affiliates\Data\AffiliateAttributionData;
use AIArmada\Affiliates\Data\AffiliateData;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AffiliateAttributed
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly AffiliateData $affiliate,
        public readonly AffiliateAttributionData $attribution
    ) {}
}
