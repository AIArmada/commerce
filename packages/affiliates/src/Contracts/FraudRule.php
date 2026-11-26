<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

use AIArmada\Affiliates\Data\ClickFraudEvidence;
use AIArmada\Affiliates\Data\ConversionFraudEvidence;
use AIArmada\Affiliates\Data\FraudFinding;

interface FraudRule
{
    public function ruleCode(): string;

    public function analyzeClick(ClickFraudEvidence $evidence): ?FraudFinding;

    public function analyzeConversion(ConversionFraudEvidence $evidence): ?FraudFinding;
}
