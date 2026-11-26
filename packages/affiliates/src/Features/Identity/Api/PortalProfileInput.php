<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

final readonly class PortalProfileInput
{
    public function __construct(
        public string $name,
        public string $handle,
        public string $contactEmail,
        public string $websiteUrl,
        public string $payoutMethodType,
        public string $payoutMethodLabel,
        public string $payoutMethodAccountRef,
    ) {}
}
