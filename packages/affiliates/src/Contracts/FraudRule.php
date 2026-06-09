<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use Illuminate\Http\Request;

interface FraudRule
{
    public function ruleCode(): string;

    public function analyzeClick(Affiliate $affiliate, Request $request, array $context): ?AffiliateFraudSignal;

    public function analyzeConversion(AffiliateConversion $conversion, array $context): ?AffiliateFraudSignal;
}
