<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Support\EventRegistrationScope;

interface EventRegistrationEligibility
{
    public function ensureEligible(EventRegistrationScope $scope): void;
}
