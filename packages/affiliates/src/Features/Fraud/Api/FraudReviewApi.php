<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud\Api;

use AIArmada\Affiliates\Enums\FraudSignalStatus;

interface FraudReviewApi
{
    public function execute(string $signalId, FraudSignalStatus $status, ?string $reviewedBy, ?string $reviewNotes = null, bool $rejectLinkedConversion = false): FraudReviewResult;
}
