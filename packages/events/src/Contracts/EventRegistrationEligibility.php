<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Data\EventRegistrationScopeData;

interface EventRegistrationEligibility
{
    public function ensureEligible(EventRegistrationScopeData $scope): void;
}
