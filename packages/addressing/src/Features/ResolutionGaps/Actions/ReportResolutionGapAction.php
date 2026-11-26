<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Features\ResolutionGaps\Actions;

use AIArmada\Addressing\Contracts\ResolutionGapReporter;

final readonly class ReportResolutionGapAction implements ResolutionGapReporter
{
    public function __construct(private LogAddressResolutionGapAction $logger) {}

    public function report(string $source, string $countryCode, string $role, string $value, string $reason = 'unmatched', ?array $context = null): void
    {
        $this->logger->execute($source, $countryCode, $role, $value, $reason, $context);
    }
}
