<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventPassIssuanceService
{
    /**
     * Issue every admission pass the registration is entitled to.
     *
     * @return list<string>
     */
    public function issueForRegistration(string $registrationId): array;
}
